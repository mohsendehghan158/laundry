"use client";

import { ExclamationCircleOutlined, FullscreenExitOutlined, FullscreenOutlined, PauseCircleOutlined, ThunderboltFilled, ToolOutlined } from "@ant-design/icons";
import { useQueryClient } from "@tanstack/react-query";
import { App, Badge, Button, Card, Col, Empty, Flex, Row, Select, Space, Statistic, Tag, Typography, theme } from "antd";
import Link from "next/link";
import { useTranslations } from "next-intl";
import { useEffect, useMemo, useRef, useState } from "react";
import type { BoardColumn, BoardOrder } from "@/shared/api/types";
import { useFeedbackSound } from "@/shared/hooks/useFeedbackSound";
import { usePermission } from "@/shared/hooks/usePermission";
import { useRememberedId } from "@/shared/hooks/useRememberedId";
import { dayjs, formatDateTime } from "@/shared/lib/date";
import { formatNumber } from "@/shared/lib/digits";
import { useBoardChannel } from "@/shared/realtime/useBoardChannel";
import { PageHeader } from "@/shared/ui/PageHeader";
import { productionKeys, useProductionBoard } from "../api";

function OrderCard({ order, large }: { order: BoardOrder; large: boolean }) {
  const t = useTranslations();
  const { token } = theme.useToken();
  const due = dayjs(order.due_at);
  const sameDay = due.isSame(dayjs(), "day");

  return (
    <Card
      size="small"
      data-testid="board-order"
      style={{ borderInlineStart: `4px solid ${order.is_overdue ? token.colorError : order.is_express ? token.colorWarning : token.colorBorderSecondary}` }}
      styles={{ body: { padding: large ? 12 : 8 } }}
    >
      <Flex justify="space-between" align="center" gap={8}>
        <Link href={`/orders/${order.id}`} className="ltr" style={{ fontWeight: 600, fontSize: large ? 18 : 14 }}>
          {order.ticket_number}
        </Link>
        <Typography.Text strong style={{ fontSize: large ? 18 : 14 }}>
          {t("board.pieces", { count: formatNumber(order.pieces), total: formatNumber(order.total_pieces) })}
        </Typography.Text>
      </Flex>
      <Typography.Text type="secondary" ellipsis style={{ display: "block", fontSize: large ? 15 : 12 }}>
        {order.customer_name}
      </Typography.Text>
      <Space size={4} wrap style={{ marginTop: 4 }}>
        <Tag color={order.is_overdue ? "red" : undefined} icon={order.is_overdue ? <ExclamationCircleOutlined /> : undefined}>
          {sameDay ? t("board.dueAt", { time: formatDateTime(order.due_at).split(" ")[1] }) : formatDateTime(order.due_at)}
        </Tag>
        {order.is_express && (
          <Tag color="volcano" icon={<ThunderboltFilled />}>
            {t("station.express")}
          </Tag>
        )}
        {order.rework > 0 && (
          <Tag color="orange" icon={<ToolOutlined />}>
            {t("board.rework", { count: order.rework })}
          </Tag>
        )}
        {order.on_hold > 0 && (
          <Tag color="magenta" icon={<PauseCircleOutlined />}>
            {t("board.onHold", { count: order.on_hold })}
          </Tag>
        )}
      </Space>
    </Card>
  );
}

function Column({ column, large }: { column: BoardColumn; large: boolean }) {
  const t = useTranslations();
  const title = column.station ? column.station.name : t(`board.columns.${column.key as "waiting" | "assembled"}`);
  return (
    <Card
      data-testid="board-column"
      title={
        <Flex justify="space-between" align="center">
          <span style={{ fontSize: large ? 18 : undefined }}>
            {title}
            {column.station?.kind === "qc" && <Tag style={{ marginInlineStart: 6 }}>{t("stations.kinds.qc")}</Tag>}
          </span>
          <Badge count={column.pieces} showZero overflowCount={999} color="#1668dc" />
        </Flex>
      }
      style={{ width: large ? 320 : 270, flex: "none" }}
      styles={{ body: { padding: 8, maxHeight: large ? "calc(100vh - 260px)" : "calc(100vh - 380px)", minHeight: 160, overflowY: "auto" } }}
    >
      {column.orders.length === 0 ? (
        <Typography.Text type="secondary">{t("board.emptyColumn")}</Typography.Text>
      ) : (
        <Flex vertical gap={8}>
          {column.orders.map((order) => (
            <OrderCard key={order.id} order={order} large={large} />
          ))}
        </Flex>
      )}
    </Card>
  );
}

/**
 * The production board of a branch: pieces in work by station, the most
 * urgent orders on top. Live over the socket when available, otherwise
 * refreshed every few seconds; full screen for a wall TV.
 */
export function BoardPage() {
  const t = useTranslations();
  const { token } = theme.useToken();
  const { notification } = App.useApp();
  const beep = useFeedbackSound();
  const queryClient = useQueryClient();
  const { me } = usePermission();
  const branches = useMemo(() => [...(me?.branches ?? [])].sort((a, b) => Number(b.type === "plant") - Number(a.type === "plant")), [me]);
  const [remembered, setBranch] = useRememberedId("board.branch");
  const branchId = branches.some((b) => b.id === remembered) ? remembered : (branches[0]?.id ?? null);
  const wall = useRef<HTMLDivElement>(null);
  const [fullscreen, setFullscreen] = useState(false);

  const live = useBoardChannel(branchId, {
    onChange: () => queryClient.invalidateQueries({ queryKey: productionKeys.board(branchId ?? 0) }),
    onOverdue: (tickets) => {
      beep("warn");
      notification.warning({ title: t("board.overdueAlert"), description: tickets.join("، "), placement: "bottomLeft", duration: 0 });
      queryClient.invalidateQueries({ queryKey: productionKeys.board(branchId ?? 0) });
    },
  });
  const board = useProductionBoard(branchId, live);
  const data = board.data;

  useEffect(() => {
    const update = () => setFullscreen(document.fullscreenElement !== null);
    document.addEventListener("fullscreenchange", update);
    return () => document.removeEventListener("fullscreenchange", update);
  }, []);

  const toggleFullscreen = () => {
    if (document.fullscreenElement) {
      void document.exitFullscreen();
    } else {
      void wall.current?.requestFullscreen?.();
    }
  };

  const counters: { key: keyof NonNullable<typeof data>["counters"]; danger?: boolean }[] = [
    { key: "pieces" },
    { key: "orders" },
    { key: "due_today" },
    { key: "overdue", danger: true },
    { key: "express" },
    { key: "rework" },
    { key: "on_hold" },
  ];

  return (
    <div ref={wall} style={{ background: token.colorBgLayout, padding: fullscreen ? 24 : 0, minHeight: fullscreen ? "100vh" : undefined, overflow: fullscreen ? "auto" : undefined }}>
      <PageHeader
        title={data ? t("board.titleFor", { branch: data.branch.name }) : t("board.title")}
        subtitle={
          <Space size={12} wrap>
            <Badge status={live ? "processing" : "default"} text={live ? t("board.live") : t("board.polling")} />
            {data && <Typography.Text type="secondary">{t("board.updated", { time: formatDateTime(data.generated_at).split(" ")[1] })}</Typography.Text>}
          </Space>
        }
        extra={
          <>
            {branches.length > 1 && (
              <Select
                aria-label={t("board.branch")}
                value={branchId ?? undefined}
                onChange={(id: number) => setBranch(id)}
                options={branches.map((b) => ({ value: b.id, label: b.name }))}
                style={{ width: 200 }}
              />
            )}
            <Button icon={fullscreen ? <FullscreenExitOutlined /> : <FullscreenOutlined />} onClick={toggleFullscreen}>
              {fullscreen ? t("board.exitTv") : t("board.tv")}
            </Button>
          </>
        }
      />
      {branchId === null ? (
        <Empty description={t("board.noBranch")} />
      ) : (
        <>
          <Row gutter={[12, 12]} style={{ marginBottom: 16 }}>
            {counters.map(({ key, danger }) => {
              const value = data?.counters[key] ?? 0;
              return (
                <Col key={key} xs={12} sm={8} md={6} xl={3}>
                  <Card size="small" data-testid={`board-counter-${key}`}>
                    <Statistic
                      title={t(`board.counters.${key}`)}
                      value={formatNumber(value)}
                      loading={board.isLoading}
                      prefix={danger && value > 0 ? <ExclamationCircleOutlined /> : undefined}
                      styles={{ content: { color: danger && value > 0 ? token.colorError : undefined, fontSize: fullscreen ? 32 : 22 } }}
                    />
                  </Card>
                </Col>
              );
            })}
          </Row>
          <div style={{ display: "flex", gap: 12, overflowX: "auto", paddingBottom: 8 }} role="region" aria-label={t("board.columnsLabel")}>
            {(data?.columns ?? []).map((column) => (
              <Column key={column.key} column={column} large={fullscreen} />
            ))}
          </div>
        </>
      )}
    </div>
  );
}
