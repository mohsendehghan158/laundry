"use client";

import { DeleteOutlined, PrinterOutlined, SendOutlined } from "@ant-design/icons";
import { Alert, App, Button, Card, Checkbox, Col, Descriptions, Flex, InputNumber, Modal, Popconfirm, Result, Row, Skeleton, Space, Table, Tag, Typography } from "antd";
import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { errorMessage } from "@/shared/api/formErrors";
import type { TagResult, Transfer } from "@/shared/api/types";
import { useFeedbackSound } from "@/shared/hooks/useFeedbackSound";
import { usePermission } from "@/shared/hooks/usePermission";
import { formatDateTime } from "@/shared/lib/date";
import { formatNumber, inputNumberFormatter } from "@/shared/lib/digits";
import { parseAmount } from "@/shared/lib/money";
import { PageHeader } from "@/shared/ui/PageHeader";
import { useCancelTransfer, useCloseTransfer, useRemoveTransferPiece, useSendTransfer, useTransfer, useTransferPieces } from "../movementApi";
import { ScanField } from "./ScanField";
import { TagResults } from "./TagResults";
import { TRANSFER_STATUS_COLOR } from "./TransfersPage";

type TransferPiece = NonNullable<Transfer["pieces"]>[number];

/**
 * One transfer: filled by scanning at the sending branch and sent with a
 * bag count; at the other end, pieces are scanned in as they are
 * unpacked and the transfer is closed (missing pieces stay in transit).
 */
export function TransferDetailPage() {
  const t = useTranslations();
  const router = useRouter();
  const { message } = App.useApp();
  const beep = useFeedbackSound();
  const { me } = usePermission();
  const id = Number(useParams<{ id: string }>().id);
  const transfer = useTransfer(Number.isFinite(id) ? id : null);
  const scan = useTransferPieces();
  const remove = useRemoveTransferPiece();
  const send = useSendTransfer();
  const close = useCloseTransfer();
  const cancel = useCancelTransfer();
  const [results, setResults] = useState<TagResult[]>([]);
  const [bags, setBags] = useState<number | null>(1);
  const [closing, setClosing] = useState(false);
  const [bagsCounted, setBagsCounted] = useState<number | null>(null);
  const [trustAll, setTrustAll] = useState(false);

  if (transfer.isLoading) return <Skeleton active />;
  if (!transfer.data) return <Result status="404" title={t("transfers.notFound")} extra={<Link href="/transfers">{t("transfers.backToList")}</Link>} />;

  const data = transfer.data;
  const pieces = data.pieces ?? [];
  const mine = (branchId: number) => me?.permissions.includes("branches.access_all") || me?.branches.some((b) => b.id === branchId);
  const sending = data.status === "open" && mine(data.from_branch.id);
  const receiving = ["sent", "received"].includes(data.status) && mine(data.to_branch.id);
  const missing = pieces.filter((p) => !p.received);
  const onError = (error: unknown) => message.error(errorMessage(error, t("common.error")));

  const onTags = (tags: string[]) =>
    scan.mutate(
      { id: data.id, tags, mode: sending ? "add" : "receive" },
      {
        onSuccess: ({ results }) => {
          setResults(results);
          beep(results.every((r) => r.ok) ? "ok" : "error");
        },
        onError,
      },
    );

  return (
    <>
      <PageHeader
        title={
          <Space>
            <span className="ltr">{data.number}</span>
            <Tag color={TRANSFER_STATUS_COLOR[data.status]}>{t(`transfers.statuses.${data.status}`)}</Tag>
          </Space>
        }
        subtitle={t("transfers.route", { from: data.from_branch.name, to: data.to_branch.name })}
        extra={
          <>
            <Button icon={<PrinterOutlined />} onClick={() => window.open(`/print/transfers/${data.id}`, "_blank", "noopener")} disabled={pieces.length === 0}>
              {t("transfers.manifest")}
            </Button>
            {sending && (
              <Popconfirm
                title={t("transfers.cancelConfirm")}
                okText={t("common.yes")}
                cancelText={t("common.no")}
                onConfirm={() => cancel.mutate(data.id, { onSuccess: () => router.push("/transfers"), onError })}
              >
                <Button danger>{t("transfers.cancel")}</Button>
              </Popconfirm>
            )}
          </>
        }
      />
      <Row gutter={[16, 16]}>
        <Col xs={24} lg={16}>
          {(sending || receiving) && (
            <Card style={{ marginBottom: 16 }} title={sending ? t("transfers.scanToAdd") : t("transfers.scanToReceive")}>
              <Flex vertical gap={12}>
                <ScanField label={t("station.scanLabel")} placeholder={t("station.scanPlaceholder")} onTags={onTags} disabled={scan.isPending} />
                <TagResults results={results} />
              </Flex>
            </Card>
          )}
          {data.status === "received" && missing.length > 0 && (
            <Alert type="warning" showIcon style={{ marginBottom: 16 }} title={t("transfers.missing", { count: missing.length })} description={missing.map((p) => p.tag_code).join("، ")} />
          )}
          <Card styles={{ body: { padding: 0 } }} title={t("transfers.manifestTitle", { count: pieces.length })}>
            <Table<TransferPiece>
              rowKey="id"
              size="small"
              dataSource={pieces}
              pagination={false}
              scroll={{ x: "max-content" }}
              locale={{ emptyText: t("transfers.noPieces") }}
              columns={[
                { key: "tag", dataIndex: "tag_code", title: t("orders.detail.tag"), render: (v: string) => <Typography.Text code className="ltr">{v}</Typography.Text> },
                { key: "item", title: t("orders.detail.item"), render: (_v, p) => [p.item_name, p.color_name].filter(Boolean).join(" · ") },
                { key: "ticket", dataIndex: "ticket_number", title: t("orders.columns.ticket"), render: (v: string) => <span className="ltr">{v}</span> },
                ...(data.status === "open"
                  ? []
                  : [{ key: "received", title: t("transfers.fields.arrived"), render: (_v: unknown, p: TransferPiece) => (p.received ? <Tag color="green">{t("common.yes")}</Tag> : <Tag color={data.status === "received" ? "red" : undefined}>{t("common.no")}</Tag>) }]),
                ...(sending
                  ? [{
                      key: "remove",
                      title: "",
                      width: 60,
                      render: (_v: unknown, p: TransferPiece) => (
                        <Button type="text" danger icon={<DeleteOutlined />} aria-label={`${t("common.delete")} ${p.tag_code}`} onClick={() => remove.mutate({ id: data.id, pieceId: p.id }, { onError })} />
                      ),
                    }]
                  : []),
              ]}
            />
          </Card>
        </Col>
        <Col xs={24} lg={8}>
          <Card>
            <Descriptions column={1} size="small">
              <Descriptions.Item label={t("transfers.fields.pieces")}>
                {data.status === "open" ? formatNumber(data.piece_count) : `${formatNumber(data.received_count)} / ${formatNumber(data.piece_count)}`}
              </Descriptions.Item>
              <Descriptions.Item label={t("transfers.fields.bags")}>{data.bags_sent === null ? "—" : formatNumber(data.bags_sent)}</Descriptions.Item>
              {data.bags_received !== null && <Descriptions.Item label={t("transfers.fields.bagsReceived")}>{formatNumber(data.bags_received)}</Descriptions.Item>}
              {data.note && <Descriptions.Item label={t("transfers.fields.note")}>{data.note}</Descriptions.Item>}
              <Descriptions.Item label={t("transfers.fields.createdBy")}>{data.creator_name ?? "—"}</Descriptions.Item>
              {data.sent_at && <Descriptions.Item label={t("transfers.fields.sentAt")}>{`${formatDateTime(data.sent_at)} · ${data.sender_name ?? ""}`}</Descriptions.Item>}
              {data.received_at && <Descriptions.Item label={t("transfers.fields.receivedAt")}>{`${formatDateTime(data.received_at)} · ${data.receiver_name ?? ""}`}</Descriptions.Item>}
            </Descriptions>
            {sending && (
              <Flex vertical gap={8} style={{ marginTop: 12 }}>
                <Typography.Text>{t("transfers.bagsPrompt")}</Typography.Text>
                <Flex gap={8}>
                  <InputNumber<number> aria-label={t("transfers.fields.bags")} min={1} max={500} value={bags} onChange={setBags} formatter={inputNumberFormatter} parser={(text) => parseAmount(text) ?? 0} style={{ width: 100 }} />
                  <Button
                    type="primary"
                    icon={<SendOutlined />}
                    disabled={pieces.length === 0 || !bags}
                    loading={send.isPending}
                    onClick={() => send.mutate({ id: data.id, bags: bags ?? 1 }, { onSuccess: () => message.success(t("transfers.sent")), onError })}
                  >
                    {t("transfers.send")}
                  </Button>
                </Flex>
              </Flex>
            )}
            {receiving && data.status === "sent" && (
              <Button type="primary" block style={{ marginTop: 12 }} onClick={() => setClosing(true)}>
                {t("transfers.close")}
              </Button>
            )}
          </Card>
        </Col>
      </Row>
      <Modal
        open={closing}
        title={t("transfers.close")}
        okText={t("transfers.closeConfirm")}
        cancelText={t("common.cancel")}
        confirmLoading={close.isPending}
        onCancel={() => setClosing(false)}
        onOk={() =>
          close.mutate(
            { id: data.id, bags: bagsCounted, all: trustAll },
            {
              onSuccess: () => {
                setClosing(false);
                message.success(t("transfers.closed"));
              },
              onError,
            },
          )
        }
        destroyOnHidden
      >
        <Flex vertical gap={12}>
          {missing.length > 0 && !trustAll && <Alert type="warning" showIcon title={t("transfers.closeMissing", { count: missing.length })} />}
          <Flex gap={8} align="center">
            <Typography.Text>{t("transfers.bagsCounted")}</Typography.Text>
            <InputNumber<number> aria-label={t("transfers.bagsCounted")} min={0} max={500} value={bagsCounted} onChange={setBagsCounted} formatter={inputNumberFormatter} parser={(text) => parseAmount(text) ?? 0} />
          </Flex>
          {bagsCounted !== null && data.bags_sent !== null && bagsCounted !== data.bags_sent && (
            <Alert type="error" showIcon title={t("transfers.bagMismatch", { sent: data.bags_sent, counted: bagsCounted })} />
          )}
          {missing.length > 0 && (
            <Checkbox checked={trustAll} onChange={(e) => setTrustAll(e.target.checked)}>
              {t("transfers.trustAll", { count: missing.length })}
            </Checkbox>
          )}
        </Flex>
      </Modal>
    </>
  );
}
