"use client";

import { CheckCircleFilled, ClockCircleOutlined, StopOutlined, ThunderboltFilled } from "@ant-design/icons";
import { Alert, App, Button, Card, Col, Empty, Flex, Input, Progress, Row, Space, Tag, Typography, theme, type InputRef } from "antd";
import Link from "next/link";
import { useTranslations } from "next-intl";
import { useRef, useState } from "react";
import { errorMessage } from "@/shared/api/formErrors";
import type { AssemblyPiece, AssemblyState } from "@/shared/api/types";
import { useFeedbackSound } from "@/shared/hooks/useFeedbackSound";
import { formatDateTime } from "@/shared/lib/date";
import { PageHeader } from "@/shared/ui/PageHeader";
import { useAssemblyScan, useCompleteAssembly } from "../api";
import { normalizeTag } from "../tags";
import { RackPicker, type RackChoice } from "./RackPicker";

function PieceTile({ piece, highlighted }: { piece: AssemblyPiece; highlighted: boolean }) {
  const t = useTranslations();
  const { token } = theme.useToken();
  const gathered = piece.status === "assembled" || piece.status === "ready" || piece.status === "handed_over";
  const style = gathered
    ? { background: token.colorSuccessBg, borderColor: token.colorSuccessBorder }
    : piece.blocker
      ? { background: token.colorWarningBg, borderColor: token.colorWarningBorder }
      : {};

  return (
    <Card size="small" data-testid="assembly-piece" data-gathered={gathered} style={{ ...style, outline: highlighted ? `2px solid ${token.colorPrimary}` : undefined }}>
      <Flex vertical gap={2}>
        <Space>
          {gathered ? <CheckCircleFilled style={{ color: token.colorSuccess }} /> : piece.blocker ? <StopOutlined style={{ color: token.colorWarning }} /> : <ClockCircleOutlined />}
          <Typography.Text strong className="ltr">
            {piece.tag_code}
          </Typography.Text>
        </Space>
        <Typography.Text>
          {piece.item_name}
          {piece.color_name ? ` · ${piece.color_name}` : ""}
        </Typography.Text>
        <Typography.Text type={piece.blocker ? "warning" : "secondary"} style={{ fontSize: 12 }}>
          {piece.blocker ?? (piece.slot ? t("assembly.inSlot", { slot: piece.slot }) : t(gathered ? "assembly.gathered" : "assembly.waiting"))}
        </Typography.Text>
      </Flex>
    </Card>
  );
}

function OrderPanel({ state, lastPiece, onDone }: { state: AssemblyState; lastPiece: number | null; onDone: (message: string) => void }) {
  const t = useTranslations();
  const { message } = App.useApp();
  const beep = useFeedbackSound();
  const complete = useCompleteAssembly();
  const [rack, setRack] = useState<RackChoice>({ slotId: state.suggestion?.id ?? null, location: "" });
  const needed = state.counts.total - state.counts.lost - state.counts.ready;
  const missing = state.pieces.filter((p) => ["received", "in_process"].includes(p.status) && p.flag !== "lost");

  const submit = (partial: boolean) =>
    complete.mutate(
      { orderId: state.order.id, input: { rack_slot_id: rack.slotId, rack_location: rack.slotId ? null : rack.location.trim() || null, partial } },
      {
        onSuccess: (result) => {
          beep("ok");
          onDone(t(result.order.status === "ready" ? "assembly.readyDone" : "assembly.partialDone", { ticket: result.order.ticket_number, rack: result.order.rack_location ?? "—" }));
        },
        onError: (error) => {
          beep("error");
          message.error(errorMessage(error, t("common.error")));
        },
      },
    );

  return (
    <Row gutter={[16, 16]}>
      <Col xs={24} lg={16}>
        <Card
          title={
            <Space wrap>
              <Link href={`/orders/${state.order.id}`} className="ltr">
                {state.order.ticket_number}
              </Link>
              <span>{state.order.customer_name}</span>
              {state.order.is_express && (
                <Tag color="volcano" icon={<ThunderboltFilled />}>
                  {t("station.express")}
                </Tag>
              )}
              <Tag color={state.order.is_overdue ? "red" : undefined}>{t("station.due", { date: formatDateTime(state.order.due_at) })}</Tag>
            </Space>
          }
        >
          <Progress percent={needed === 0 ? 100 : Math.round((state.counts.assembled / needed) * 100)} format={() => t("assembly.progress", { gathered: state.counts.assembled, total: needed })} style={{ marginBottom: 16 }} />
          {state.order.notes && <Alert type="info" showIcon title={state.order.notes} style={{ marginBottom: 12 }} />}
          <Row gutter={[12, 12]}>
            {state.pieces.map((piece) => (
              <Col key={piece.id} xs={12} md={8}>
                <PieceTile piece={piece} highlighted={piece.id === lastPiece} />
              </Col>
            ))}
          </Row>
        </Card>
      </Col>
      <Col xs={24} lg={8}>
        <Card title={t("assembly.rackTitle")}>
          <Flex vertical gap={12}>
            {missing.length > 0 && <Alert type="warning" showIcon title={t("assembly.missing", { count: missing.length })} description={missing.map((p) => p.tag_code).join("، ")} />}
            <RackPicker branchId={state.order.branch.id} suggestion={state.suggestion} value={rack} onChange={setRack} />
            <Button type="primary" size="large" block disabled={!state.complete} loading={complete.isPending} onClick={() => submit(false)}>
              {t("assembly.complete")}
            </Button>
            {!state.complete && state.counts.assembled > 0 && (
              <Button block loading={complete.isPending} onClick={() => submit(true)}>
                {t("assembly.partial")}
              </Button>
            )}
          </Flex>
        </Card>
      </Col>
    </Row>
  );
}

/**
 * The assembly table: scan each finished piece; the screen gathers it with
 * the rest of its order, shows what is still missing and where, and racks
 * the order (in the suggested slot) once every piece is there.
 */
export function AssemblyPage() {
  const t = useTranslations();
  const beep = useFeedbackSound();
  const input = useRef<InputRef>(null);
  const scan = useAssemblyScan();
  const [text, setText] = useState("");
  const [state, setState] = useState<AssemblyState | null>(null);
  const [lastPiece, setLastPiece] = useState<number | null>(null);
  const [notice, setNotice] = useState<{ type: "success" | "error"; text: string } | null>(null);

  const focus = () => setTimeout(() => input.current?.focus(), 0);

  const submit = () => {
    const tag = normalizeTag(text);
    setText("");
    if (!tag) return;
    scan.mutate(tag, {
      onSuccess: ({ data, scanned_piece_id }) => {
        setState(data);
        setLastPiece(scanned_piece_id);
        setNotice(null);
        beep(data.complete ? "warn" : "ok");
      },
      onError: (error) => {
        setNotice({ type: "error", text: errorMessage(error, t("common.error")) });
        beep("error");
      },
      onSettled: focus,
    });
  };

  return (
    <>
      <PageHeader title={t("assembly.title")} subtitle={t("assembly.subtitle")} />
      <Card style={{ marginBottom: 16 }}>
        <Input
          ref={input}
          autoFocus
          size="large"
          aria-label={t("station.scanLabel")}
          placeholder={t("station.scanPlaceholder")}
          value={text}
          onChange={(e) => setText(e.target.value)}
          onPressEnter={submit}
          disabled={scan.isPending}
          className="ltr"
          style={{ fontSize: 28, height: 64, textAlign: "center" }}
        />
        {state?.complete && <Typography.Text type="success" strong style={{ display: "block", marginTop: 8 }}>{t("assembly.allHere")}</Typography.Text>}
      </Card>
      {notice && <Alert type={notice.type} showIcon title={notice.text} style={{ marginBottom: 16 }} data-testid="assembly-notice" />}
      {state ? (
        <OrderPanel
          key={`${state.order.id}-${state.suggestion?.id ?? 0}`}
          state={state}
          lastPiece={lastPiece}
          onDone={(text) => {
            setNotice({ type: "success", text });
            setState(null);
            focus();
          }}
        />
      ) : (
        <Empty description={t("assembly.hint")} />
      )}
    </>
  );
}
