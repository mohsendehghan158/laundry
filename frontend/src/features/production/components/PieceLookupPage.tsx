"use client";

import { EnvironmentOutlined, PauseCircleOutlined, PlayCircleOutlined, ThunderboltFilled } from "@ant-design/icons";
import { Alert, App, Button, Card, Col, Descriptions, Empty, Flex, Input, Modal, Row, Skeleton, Space, Tag, Timeline, Typography } from "antd";
import Link from "next/link";
import { useTranslations } from "next-intl";
import { parseAsString, useQueryState } from "nuqs";
import { useState } from "react";
import { isApiError } from "@/shared/api/client";
import { errorMessage } from "@/shared/api/formErrors";
import type { OrderStatus, PieceDetail } from "@/shared/api/types";
import { usePermission } from "@/shared/hooks/usePermission";
import { formatDateTime } from "@/shared/lib/date";
import { PageHeader } from "@/shared/ui/PageHeader";
import { usePiece, usePieceHold } from "../api";
import { PIECE_FLAG_COLOR, PIECE_STATUS_COLOR } from "../status";
import { normalizeTag } from "../tags";

/** Where the piece is right now, in one sentence. */
function WhereIs({ piece }: { piece: PieceDetail }) {
  const t = useTranslations("pieceLookup.where");
  const branch = piece.branch?.name ?? "—";
  if (piece.status === "handed_over") return t("handedOver", { date: formatDateTime(piece.handed_over_at) });
  if (piece.flag === "in_transit") return t("inTransit", { from: branch });
  if (piece.flag === "outsourced") return t("outsourced");
  if (piece.flag === "lost") return t("lost");
  if (piece.status === "ready") return t("rack", { branch, rack: piece.rack_location ?? "—" });
  if (piece.station) return t("station", { branch, station: piece.station.name });
  return t("branch", { branch });
}

function HoldButton({ piece }: { piece: PieceDetail }) {
  const t = useTranslations();
  const { message } = App.useApp();
  const hold = usePieceHold();
  const [open, setOpen] = useState(false);
  const [reason, setReason] = useState("");

  if (piece.flag === "on_hold") {
    return (
      <Button
        icon={<PlayCircleOutlined />}
        loading={hold.isPending}
        onClick={() => hold.mutate({ id: piece.id, reason: null }, { onError: (error) => message.error(errorMessage(error, t("common.error"))) })}
      >
        {t("pieceLookup.release")}
      </Button>
    );
  }
  if (piece.status === "ready" || piece.status === "handed_over") {
    return null;
  }
  return (
    <>
      <Button icon={<PauseCircleOutlined />} onClick={() => setOpen(true)}>
        {t("pieceLookup.hold")}
      </Button>
      <Modal
        open={open}
        title={t("pieceLookup.holdTitle", { tag: piece.tag_code })}
        okText={t("pieceLookup.hold")}
        cancelText={t("common.cancel")}
        okButtonProps={{ disabled: reason.trim() === "" }}
        confirmLoading={hold.isPending}
        onCancel={() => setOpen(false)}
        onOk={() =>
          hold.mutate(
            { id: piece.id, reason: reason.trim() },
            {
              onSuccess: () => {
                setOpen(false);
                setReason("");
              },
              onError: (error) => message.error(errorMessage(error, t("common.error"))),
            },
          )
        }
        destroyOnHidden
      >
        <Input.TextArea aria-label={t("pieceLookup.holdReason")} placeholder={t("pieceLookup.holdReason")} value={reason} onChange={(e) => setReason(e.target.value)} maxLength={500} autoFocus />
      </Modal>
    </>
  );
}

/**
 * "Where is this piece?": scan or type a tag and see where it is, what
 * state it is in, its order and everything that happened to it.
 */
export function PieceLookupPage() {
  const t = useTranslations();
  const { can } = usePermission();
  const [tag, setTag] = useQueryState("tag", parseAsString);
  const [text, setText] = useState(tag ?? "");
  const piece = usePiece(tag);
  const data = piece.data;

  return (
    <>
      <PageHeader title={t("pieceLookup.title")} subtitle={t("pieceLookup.subtitle")} />
      <Card style={{ marginBottom: 16 }}>
        <Input.Search
          autoFocus
          size="large"
          enterButton={t("common.search")}
          aria-label={t("pieceLookup.searchLabel")}
          placeholder={t("pieceLookup.searchPlaceholder")}
          value={text}
          onChange={(e) => setText(e.target.value)}
          onSearch={(value) => {
            const code = normalizeTag(value);
            setText(code);
            void setTag(code || null);
          }}
          className="ltr"
        />
      </Card>

      {!tag ? (
        <Empty description={t("pieceLookup.hint")} />
      ) : piece.isLoading ? (
        <Skeleton active />
      ) : piece.isError ? (
        <Alert type="error" showIcon title={isApiError(piece.error, 404) ? t("pieceLookup.notFound", { tag }) : errorMessage(piece.error, t("common.error"))} />
      ) : data ? (
        <Row gutter={[16, 16]}>
          <Col xs={24} lg={14}>
            <Card
              title={
                <Space wrap>
                  <span className="ltr">{data.tag_code}</span>
                  <Tag color={PIECE_STATUS_COLOR[data.status]}>{t(`pieces.statuses.${data.status}`)}</Tag>
                  {data.flag && <Tag color={PIECE_FLAG_COLOR[data.flag]}>{t(`pieceFlags.${data.flag}`)}</Tag>}
                </Space>
              }
              extra={can("production.scan") && <HoldButton piece={data} />}
            >
              <Alert type={data.flag && data.flag !== "damaged" ? "warning" : "info"} icon={<EnvironmentOutlined />} showIcon title={<WhereIs piece={data} />} description={data.flag_note} style={{ marginBottom: 16 }} />
              <Descriptions column={{ xs: 1, sm: 2 }} size="small">
                <Descriptions.Item label={t("pieceLookup.item")}>
                  {data.item_name} · {data.service_name}
                </Descriptions.Item>
                <Descriptions.Item label={t("pieceLookup.color")}>{[data.color_name, data.brand].filter(Boolean).join(" · ") || "—"}</Descriptions.Item>
                {data.defects.length > 0 && <Descriptions.Item label={t("pieceLookup.defects")}>{data.defects.join("، ")}</Descriptions.Item>}
                {data.notes && <Descriptions.Item label={t("pieceLookup.notes")}>{data.notes}</Descriptions.Item>}
                <Descriptions.Item label={t("pieceLookup.lastScan")}>{formatDateTime(data.last_scanned_at) || "—"}</Descriptions.Item>
              </Descriptions>
            </Card>
            <Card title={t("pieceLookup.order")} style={{ marginTop: 16 }}>
              <Descriptions column={{ xs: 1, sm: 2 }} size="small">
                <Descriptions.Item label={t("pieceLookup.ticket")}>
                  <Space>
                    {can("orders.view") ? (
                      <Link href={`/orders/${data.order.id}`} className="ltr">
                        {data.order.ticket_number}
                      </Link>
                    ) : (
                      <span className="ltr">{data.order.ticket_number}</span>
                    )}
                    <Tag>{t(`orders.statuses.${data.order.status as OrderStatus}`)}</Tag>
                  </Space>
                </Descriptions.Item>
                <Descriptions.Item label={t("pieceLookup.customer")}>{data.order.customer_name}</Descriptions.Item>
                <Descriptions.Item label={t("pieceLookup.due")}>
                  <Space>
                    {formatDateTime(data.order.due_at)}
                    {data.order.is_overdue && <Tag color="red">{t("pieceLookup.overdue")}</Tag>}
                    {data.order.is_express && (
                      <Tag color="volcano" icon={<ThunderboltFilled />}>
                        {t("station.express")}
                      </Tag>
                    )}
                  </Space>
                </Descriptions.Item>
                <Descriptions.Item label={t("pieceLookup.pickupBranch")}>{data.order.branch.name}</Descriptions.Item>
                <Descriptions.Item label={t("pieceLookup.pieces")}>{t("station.pieceCount", { count: data.order.piece_count })}</Descriptions.Item>
              </Descriptions>
            </Card>
          </Col>
          <Col xs={24} lg={10}>
            <Card title={t("pieceLookup.history")}>
              {data.events.length === 0 ? (
                <Typography.Text type="secondary">{t("pieceLookup.noHistory")}</Typography.Text>
              ) : (
                <Timeline
                  items={[...data.events].reverse().map((event) => ({
                    key: event.id,
                    color: event.event === "qc_failed" || event.event === "lost" ? "red" : event.event === "qc_passed" || event.event === "racked" ? "green" : "blue",
                    children: (
                      <Flex vertical>
                        <Typography.Text strong>
                          {t(`pieceEvents.${event.event}`)}
                          {event.station ? ` · ${event.station.name}` : ""}
                        </Typography.Text>
                        {event.note && <Typography.Text>{event.note}</Typography.Text>}
                        <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                          {[formatDateTime(event.created_at), event.branch?.name, event.user_name].filter(Boolean).join(" · ")}
                        </Typography.Text>
                      </Flex>
                    ),
                  }))}
                />
              )}
            </Card>
          </Col>
        </Row>
      ) : null}
    </>
  );
}
