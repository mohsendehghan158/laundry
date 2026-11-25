"use client";

import { PhoneOutlined } from "@ant-design/icons";
import { useQuery } from "@tanstack/react-query";
import { Alert, Card, Descriptions, Flex, Result, Skeleton, Steps, Tag, Typography } from "antd";
import { useParams } from "next/navigation";
import { useTranslations } from "next-intl";
import { api, isApiError } from "@/shared/api/client";
import type { PublicTracking } from "@/shared/api/types";
import { formatDateTime } from "@/shared/lib/date";
import { formatNumber } from "@/shared/lib/digits";
import { formatMoney } from "@/shared/lib/money";

const STEPS = ["received", "in_process", "ready", "completed"] as const;

/**
 * What the customer sees when they open the link in their SMS or scan
 * the receipt: where the order is, when it is due and what is owed.
 */
export function TrackingPage() {
  const t = useTranslations("tracking");
  const code = useParams<{ code: string }>().code;
  const tracking = useQuery({
    queryKey: ["tracking", code],
    queryFn: async () => (await api.get<{ data: PublicTracking }>(`/public/track/${code}`)).data,
    retry: false,
    refetchInterval: 60_000,
  });

  const shell = (children: React.ReactNode) => (
    <main style={{ minHeight: "100vh", display: "flex", justifyContent: "center", padding: 16 }}>
      <div style={{ width: "100%", maxWidth: 520 }}>{children}</div>
    </main>
  );

  if (tracking.isLoading) {
    return shell(<Skeleton active />);
  }
  if (tracking.isError || !tracking.data) {
    return shell(<Result status="404" title={isApiError(tracking.error, 404) ? t("notFound") : t("error")} />);
  }

  const data = tracking.data;
  const cancelled = data.status === "cancelled";
  const current = cancelled ? 0 : Math.max(0, STEPS.indexOf(data.status as (typeof STEPS)[number]));
  const at = (status: string) => data.history.find((h) => h.status === status)?.at;

  const money = (rial: number) => `${formatMoney(rial, data.currency_unit)} ${t(data.currency_unit)}`;

  return shell(
    <Flex vertical gap={16}>
      <Flex vertical align="center" gap={4} style={{ marginTop: 24 }}>
        <Typography.Title level={3} style={{ margin: 0 }}>
          {data.organization}
        </Typography.Title>
        <Typography.Text type="secondary">{data.branch.name}</Typography.Text>
      </Flex>
      <Card>
        <Flex justify="space-between" align="center" style={{ marginBottom: 16 }}>
          <Typography.Text strong className="ltr" style={{ fontSize: 22 }}>
            {data.ticket_number}
          </Typography.Text>
          <Tag color={cancelled ? "red" : data.status === "ready" ? "green" : data.status === "completed" ? "default" : "blue"} style={{ fontSize: 14 }}>
            {t(`statuses.${data.status}` as never)}
          </Tag>
        </Flex>
        {cancelled ? (
          <Alert type="error" showIcon title={t("cancelled")} />
        ) : (
          <Steps
            orientation="vertical"
            size="small"
            current={current}
            items={STEPS.map((step) => ({
              title: t(`statuses.${step}`),
              content: at(step) ? formatDateTime(at(step)) : step === "ready" && current < 2 ? `${t("expected")} ${formatDateTime(data.due_at)}` : undefined,
            }))}
          />
        )}
        {data.status === "ready" && <Alert type="success" showIcon title={t("readyNotice")} style={{ marginTop: 16 }} />}
      </Card>
      <Card>
        <Descriptions column={1} size="small">
          <Descriptions.Item label={t("received")}>{formatDateTime(data.received_at)}</Descriptions.Item>
          <Descriptions.Item label={t("due")}>{formatDateTime(data.due_at)}</Descriptions.Item>
          <Descriptions.Item label={t("pieces")}>{formatNumber(data.piece_count)}</Descriptions.Item>
          <Descriptions.Item label={t("total")}>{money(data.total)}</Descriptions.Item>
          <Descriptions.Item label={t("balance")}>
            <Typography.Text strong type={data.balance > 0 ? "danger" : "success"}>
              {data.balance > 0 ? money(data.balance) : t("settled")}
            </Typography.Text>
          </Descriptions.Item>
        </Descriptions>
      </Card>
      {(data.branch.phone || data.branch.address) && (
        <Card size="small">
          <Flex vertical gap={4}>
            {data.branch.address && <Typography.Text type="secondary">{data.branch.address}</Typography.Text>}
            {data.branch.phone && (
              <a href={`tel:${data.branch.phone}`} className="ltr">
                <PhoneOutlined /> {data.branch.phone}
              </a>
            )}
          </Flex>
        </Card>
      )}
    </Flex>,
  );
}
