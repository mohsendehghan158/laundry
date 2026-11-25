"use client";

import { CheckCircleFilled, CloseCircleFilled, ExclamationCircleFilled, ThunderboltFilled } from "@ant-design/icons";
import { Alert, Button, Card, Col, Empty, Flex, Input, List, Row, Segmented, Select, Space, Statistic, Tag, Typography, theme, type InputRef } from "antd";
import Link from "next/link";
import { useTranslations } from "next-intl";
import { useMemo, useRef, useState } from "react";
import { useCatalogList } from "@/features/catalog/api";
import { errorMessage } from "@/shared/api/formErrors";
import type { PieceEvent, PieceFlag, ScanResult, Station } from "@/shared/api/types";
import { useFeedbackSound } from "@/shared/hooks/useFeedbackSound";
import { usePermission } from "@/shared/hooks/usePermission";
import { useRememberedId } from "@/shared/hooks/useRememberedId";
import { formatDateTime } from "@/shared/lib/date";
import { formatNumber } from "@/shared/lib/digits";
import { PageHeader } from "@/shared/ui/PageHeader";
import { useScan, useStationActivity, useStations } from "../api";
import { splitTags } from "../tags";

type Outcome = { results: ScanResult[]; error?: string };

/** How a scan went, worst first: a refusal, then a warning, then done. */
function severity(results: ScanResult[]): "error" | "warn" | "ok" {
  if (results.some((r) => !r.ok)) return "error";
  if (results.some((r) => r.warnings.length > 0)) return "warn";
  return "ok";
}

function ResultCard({ outcome }: { outcome: Outcome }) {
  const t = useTranslations();
  const { token } = theme.useToken();

  if (outcome.error) {
    return <Alert type="error" showIcon title={outcome.error} />;
  }

  const level = severity(outcome.results);
  const palette = {
    ok: { bg: token.colorSuccessBg, border: token.colorSuccessBorder, icon: <CheckCircleFilled style={{ color: token.colorSuccess }} /> },
    warn: { bg: token.colorWarningBg, border: token.colorWarningBorder, icon: <ExclamationCircleFilled style={{ color: token.colorWarning }} /> },
    error: { bg: token.colorErrorBg, border: token.colorErrorBorder, icon: <CloseCircleFilled style={{ color: token.colorError }} /> },
  }[level];

  // A batch: a summary, then only the tags that need attention.
  if (outcome.results.length > 1) {
    const failed = outcome.results.filter((r) => !r.ok);
    return (
      <Card data-testid="scan-result" data-level={level} style={{ background: palette.bg, borderColor: palette.border }}>
        <Space align="start" size={16}>
          <span style={{ fontSize: 40 }}>{palette.icon}</span>
          <div>
            <Typography.Title level={4} style={{ marginTop: 0 }}>
              {t("station.batchSummary", { ok: outcome.results.length - failed.length, failed: failed.length })}
            </Typography.Title>
            {failed.map((r) => (
              <div key={r.tag}>
                <Typography.Text strong className="ltr">
                  {r.tag}
                </Typography.Text>
                : {r.message}
              </div>
            ))}
          </div>
        </Space>
      </Card>
    );
  }

  const result = outcome.results[0];
  const piece = result.piece;
  return (
    <Card data-testid="scan-result" data-level={level} style={{ background: palette.bg, borderColor: palette.border }}>
      <Space align="start" size={16} style={{ width: "100%" }}>
        <span style={{ fontSize: 48, lineHeight: 1 }}>{palette.icon}</span>
        <Flex vertical gap={6} style={{ flex: 1 }}>
          <Typography.Title level={3} style={{ margin: 0 }} className="ltr">
            {result.tag}
          </Typography.Title>
          {result.message && (
            <Typography.Text strong style={{ fontSize: 18 }}>
              {result.message}
            </Typography.Text>
          )}
          {result.duplicate && <Typography.Text type="secondary">{t("station.duplicate")}</Typography.Text>}
          {piece && (
            <>
              <Typography.Text style={{ fontSize: 18 }}>
                {piece.item_name} · {piece.service_name}
                {piece.color_name ? ` · ${piece.color_name}` : ""}
              </Typography.Text>
              <Space wrap>
                <Link href={`/orders/${piece.order.id}`}>
                  <Tag className="ltr">{piece.order.ticket_number}</Tag>
                </Link>
                <Tag>{t("station.pieceCount", { count: piece.order.piece_count })}</Tag>
                <Tag color={piece.order.is_overdue ? "red" : undefined}>{t("station.due", { date: formatDateTime(piece.order.due_at) })}</Tag>
                {piece.order.is_express && (
                  <Tag color="volcano" icon={<ThunderboltFilled />}>
                    {t("station.express")}
                  </Tag>
                )}
                {piece.flag && <Tag color="orange">{t(`pieceFlags.${piece.flag as PieceFlag}`)}</Tag>}
              </Space>
              {(piece.defects.length > 0 || piece.notes || piece.order.notes) && (
                <Typography.Text type="secondary">{[...piece.defects, piece.notes, piece.order.notes].filter(Boolean).join(" · ")}</Typography.Text>
              )}
            </>
          )}
          {result.warnings.map((warning) => (
            <Typography.Text key={warning} type="warning" strong>
              {warning}
            </Typography.Text>
          ))}
        </Flex>
      </Space>
    </Card>
  );
}

/**
 * The screen at a workshop station: one big scan field. Scanning a tag
 * records the piece at this station and answers with a colour and a beep;
 * in batch mode tags are collected and sent together. A quality-control
 * station asks for a verdict (and a reason when failing) first.
 */
export function StationPage() {
  const t = useTranslations();
  const { me } = usePermission();
  const beep = useFeedbackSound();
  const input = useRef<InputRef>(null);
  const stations = useStations();
  const [stationId, setStationId] = useRememberedId("station.id");
  const [mode, setMode] = useState<"single" | "batch">("single");
  const [verdict, setVerdict] = useState<"pass" | "fail">("pass");
  const [reason, setReason] = useState("");
  const [defects, setDefects] = useState<number[]>([]);
  const [text, setText] = useState("");
  const [batch, setBatch] = useState<string[]>([]);
  const [outcome, setOutcome] = useState<Outcome | null>(null);
  const scan = useScan();

  const active = useMemo(() => (stations.data ?? []).filter((s) => s.is_active), [stations.data]);
  const station: Station | undefined = active.find((s) => s.id === stationId) ?? (active.length === 1 ? active[0] : undefined);
  const qc = station?.kind === "qc";
  const defectTypes = useCatalogList("defect-types", qc);
  const activity = useStationActivity(station?.id ?? null);
  const branchName = new Map((me?.branches ?? []).map((b) => [b.id, b.name]));
  const failNeedsReason = qc && verdict === "fail" && reason.trim() === "" && defects.length === 0;

  const focus = () => setTimeout(() => input.current?.focus(), 0);

  const send = (tags: string[]) => {
    if (!station || tags.length === 0) return;
    scan.mutate(
      {
        station_id: station.id,
        tags,
        ...(qc ? { passed: verdict === "pass", reason: verdict === "fail" ? reason.trim() || null : null, defect_type_ids: verdict === "fail" ? defects : [] } : {}),
      },
      {
        onSuccess: (results) => {
          setOutcome({ results });
          beep(severity(results));
          setBatch([]);
        },
        onError: (error) => {
          setOutcome({ results: [], error: errorMessage(error, t("common.error")) });
          beep("error");
        },
        onSettled: focus,
      },
    );
  };

  const submit = () => {
    const tags = splitTags(text);
    setText("");
    if (tags.length === 0) return;
    if (failNeedsReason) {
      setOutcome({ results: [], error: t("station.reasonFirst") });
      beep("error");
      return;
    }
    if (mode === "batch") {
      setBatch((current) => [...current, ...tags.filter((tag) => !current.includes(tag))]);
      beep("ok");
      return;
    }
    send(tags);
  };

  return (
    <>
      <PageHeader
        title={station ? station.name : t("station.title")}
        subtitle={station ? branchName.get(station.branch_id) : t("station.subtitle")}
        extra={
          <Select
            aria-label={t("station.choose")}
            placeholder={t("station.choose")}
            value={station?.id}
            onChange={(id: number) => {
              setStationId(id);
              setOutcome(null);
              focus();
            }}
            options={active.map((s) => ({ value: s.id, label: `${s.name} · ${branchName.get(s.branch_id) ?? ""}` }))}
            style={{ minWidth: 240 }}
            loading={stations.isLoading}
          />
        }
      />
      {!stations.isLoading && active.length === 0 ? (
        <Empty description={t("station.noStations")} />
      ) : !station ? (
        <Alert type="info" showIcon title={t("station.chooseFirst")} />
      ) : (
        <Row gutter={[16, 16]}>
          <Col xs={24} lg={16}>
            <Card>
              <Flex vertical gap={16}>
                <Flex wrap gap={12} justify="space-between">
                  <Segmented
                    value={mode}
                    onChange={(value) => {
                      setMode(value as "single" | "batch");
                      setBatch([]);
                      focus();
                    }}
                    options={[
                      { value: "single", label: t("station.single") },
                      { value: "batch", label: t("station.batch") },
                    ]}
                  />
                  {qc && (
                    <Segmented
                      size="large"
                      value={verdict}
                      onChange={(value) => {
                        setVerdict(value as "pass" | "fail");
                        focus();
                      }}
                      options={[
                        { value: "pass", label: <span style={{ color: "#389e0d", fontWeight: 600 }}>{t("station.pass")}</span> },
                        { value: "fail", label: <span style={{ color: "#cf1322", fontWeight: 600 }}>{t("station.fail")}</span> },
                      ]}
                    />
                  )}
                </Flex>
                {qc && verdict === "fail" && (
                  <Row gutter={12}>
                    <Col xs={24} md={12}>
                      <Input aria-label={t("station.reason")} placeholder={t("station.reason")} value={reason} onChange={(e) => setReason(e.target.value)} maxLength={500} />
                    </Col>
                    <Col xs={24} md={12}>
                      <Select
                        mode="multiple"
                        allowClear
                        aria-label={t("station.defects")}
                        placeholder={t("station.defects")}
                        value={defects}
                        onChange={setDefects}
                        options={(defectTypes.data ?? []).filter((d) => d.is_active).map((d) => ({ value: d.id, label: d.name }))}
                        style={{ width: "100%" }}
                      />
                    </Col>
                  </Row>
                )}
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
                {mode === "batch" && (
                  <Flex vertical gap={8}>
                    <Typography.Text type="secondary">{t("station.batchHelp")}</Typography.Text>
                    <Flex wrap gap={6}>
                      {batch.map((tag) => (
                        <Tag key={tag} closable onClose={() => setBatch((current) => current.filter((x) => x !== tag))} className="ltr">
                          {tag}
                        </Tag>
                      ))}
                    </Flex>
                    <Space>
                      <Button type="primary" size="large" disabled={batch.length === 0} loading={scan.isPending} onClick={() => send(batch)}>
                        {t("station.sendBatch", { count: batch.length })}
                      </Button>
                      <Button disabled={batch.length === 0} onClick={() => setBatch([])}>
                        {t("station.clearBatch")}
                      </Button>
                    </Space>
                  </Flex>
                )}
                {outcome && <ResultCard outcome={outcome} />}
              </Flex>
            </Card>
          </Col>
          <Col xs={24} lg={8}>
            <Flex vertical gap={16}>
              <Card>
                <Row gutter={16}>
                  <Col span={12}>
                    <Statistic title={t("station.mine")} value={formatNumber(activity.data?.today.mine ?? 0)} loading={activity.isLoading} />
                  </Col>
                  <Col span={12}>
                    <Statistic title={t("station.total")} value={formatNumber(activity.data?.today.total ?? 0)} loading={activity.isLoading} />
                  </Col>
                </Row>
              </Card>
              <Card title={t("station.recent")} size="small">
                <List
                  size="small"
                  dataSource={activity.data?.recent ?? []}
                  locale={{ emptyText: t("station.noRecent") }}
                  renderItem={(scan) => (
                    <List.Item extra={<Typography.Text type="secondary">{formatDateTime(scan.created_at).split(" ")[1]}</Typography.Text>}>
                      <Space direction="vertical" size={0}>
                        <Typography.Text className="ltr">{scan.tag_code}</Typography.Text>
                        <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                          {scan.item_name} · {t(`pieceEvents.${scan.event as PieceEvent}`)} · {scan.user_name}
                        </Typography.Text>
                      </Space>
                    </List.Item>
                  )}
                />
              </Card>
            </Flex>
          </Col>
        </Row>
      )}
    </>
  );
}
