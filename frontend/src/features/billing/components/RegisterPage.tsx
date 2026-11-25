"use client";

import { LockOutlined, MinusCircleOutlined, PlusCircleOutlined, UnlockOutlined } from "@ant-design/icons";
import { Alert, App, Button, Card, Col, Descriptions, Drawer, Empty, Flex, Form, Input, Modal, Result, Row, Select, Skeleton, Statistic, Table, Tag, Typography } from "antd";
import type { ColumnsType } from "antd/es/table";
import Link from "next/link";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { useCounterBranch } from "@/features/pos/useCounterBranch";
import { errorMessage } from "@/shared/api/formErrors";
import type { CashShift, Payment } from "@/shared/api/types";
import { useCurrencyUnit } from "@/shared/hooks/useCurrencyUnit";
import { usePermission } from "@/shared/hooks/usePermission";
import { formatDateTime } from "@/shared/lib/date";
import { formatMoney } from "@/shared/lib/money";
import { MoneyInput } from "@/shared/ui/MoneyInput";
import { PageHeader } from "@/shared/ui/PageHeader";
import { useCashMovement, useCloseShift, useCurrentShift, useOpenShift, useShift, useShifts } from "../api";

/** "کسری ۱٬۰۰۰" / "اضافه ۱٬۰۰۰" / "بدون مغایرت": words, not a sign that is ambiguous in right-to-left text. */
function Difference({ value }: { value: number }) {
  const t = useTranslations("billing");
  const unit = useCurrencyUnit();
  if (value === 0) {
    return <Typography.Text type="success">{t("noDifference")}</Typography.Text>;
  }
  return (
    <Typography.Text type={value < 0 ? "danger" : "warning"} strong>
      {value < 0 ? t("short") : t("over")} {formatMoney(Math.abs(value), unit)}
    </Typography.Text>
  );
}

/** Totals, payments and cash movements of one shift. */
function ShiftDetails({ shift }: { shift: CashShift }) {
  const t = useTranslations();
  const unit = useCurrencyUnit();
  const m = (v: number) => formatMoney(v, unit);

  const paymentColumns: ColumnsType<Payment> = [
    { key: "time", dataIndex: "created_at", title: t("billing.time"), render: (v: string) => formatDateTime(v) },
    {
      key: "ticket",
      title: t("orders.columns.ticket"),
      render: (_v, p) => (
        <Link href={`/orders/${p.order_id}`} className="ltr">
          {p.ticket_number}
        </Link>
      ),
    },
    {
      key: "kind",
      dataIndex: "kind",
      title: t("billing.kind"),
      render: (v: Payment["kind"]) => <Tag color={v === "refund" ? "red" : "green"}>{t(`billing.kinds.${v}`)}</Tag>,
    },
    { key: "method", dataIndex: "method", title: t("billing.method"), render: (v: Payment["method"]) => t(`billing.methods.${v}`) },
    { key: "amount", dataIndex: "amount", title: t("billing.amount"), render: (v: number, p) => (p.kind === "refund" ? `−${m(v)}` : m(v)) },
    { key: "user", dataIndex: "user", title: t("billing.user"), render: (u: Payment["user"]) => u?.name },
  ];

  return (
    <Flex vertical gap={16}>
      <Row gutter={[12, 12]}>
        {(["cash", "card", "transfer"] as const).map((method) => (
          <Col key={method} xs={12} md={6}>
            <Card size="small">
              <Statistic title={t(`billing.methods.${method}`)} value={m(shift.totals.by_method[method])} />
            </Card>
          </Col>
        ))}
        <Col xs={12} md={6}>
          <Card size="small">
            <Statistic title={t("billing.expectedCash")} value={m(shift.totals.expected_cash)} valueStyle={{ color: "#1677ff" }} />
          </Card>
        </Col>
      </Row>
      <Descriptions size="small" column={{ xs: 1, sm: 2 }} bordered>
        <Descriptions.Item label={t("billing.openingFloat")}>{m(shift.opening_float)}</Descriptions.Item>
        <Descriptions.Item label={t("billing.cashIn")}>{m(shift.totals.cash_in)}</Descriptions.Item>
        <Descriptions.Item label={t("billing.cashOut")}>{m(shift.totals.cash_out)}</Descriptions.Item>
        <Descriptions.Item label={t("billing.openedAt")}>
          {formatDateTime(shift.opened_at)} · {shift.opened_by?.name}
        </Descriptions.Item>
        {shift.closed_at && (
          <Descriptions.Item label={t("billing.closedAt")}>
            {formatDateTime(shift.closed_at)} · {shift.closed_by?.name}
          </Descriptions.Item>
        )}
        {shift.counted_cash !== null && <Descriptions.Item label={t("billing.countedCash")}>{m(shift.counted_cash)}</Descriptions.Item>}
        {shift.difference !== null && (
          <Descriptions.Item label={t("billing.difference")}>
            <Difference value={shift.difference} />
          </Descriptions.Item>
        )}
      </Descriptions>
      <Card size="small" title={t("billing.payments")} styles={{ body: { padding: 0 } }}>
        <Table<Payment> rowKey="id" size="small" columns={paymentColumns} dataSource={shift.payments ?? []} pagination={false} scroll={{ x: "max-content" }} locale={{ emptyText: <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} /> }} />
      </Card>
      {(shift.movements ?? []).length > 0 && (
        <Card size="small" title={t("billing.movements")}>
          <Flex vertical gap={4}>
            {(shift.movements ?? []).map((movement) => (
              <Flex key={movement.id} justify="space-between">
                <span>
                  <Tag color={movement.type === "in" ? "green" : "orange"}>{t(`billing.movementTypes.${movement.type}`)}</Tag>
                  {movement.reason}
                </span>
                <span>
                  {m(movement.amount)} · {formatDateTime(movement.created_at)}
                </span>
              </Flex>
            ))}
          </Flex>
        </Card>
      )}
    </Flex>
  );
}

export function RegisterPage() {
  const t = useTranslations();
  const { message } = App.useApp();
  const { can } = usePermission();
  const unit = useCurrencyUnit();
  const { branches, branchId, choose } = useCounterBranch();
  const current = useCurrentShift(branchId, can("shifts.operate"));
  const history = useShifts({ branch_id: branchId, per_page: 10 }, can("shifts.view") && branchId !== null);
  const open = useOpenShift();
  const close = useCloseShift();
  const movement = useCashMovement();
  const [openingFloat, setOpeningFloat] = useState<number | null>(0);
  const [closing, setClosing] = useState(false);
  const [counted, setCounted] = useState<number | null>(null);
  const [moving, setMoving] = useState<"in" | "out" | null>(null);
  const [moveAmount, setMoveAmount] = useState<number | null>(null);
  const [moveReason, setMoveReason] = useState("");
  const [viewing, setViewing] = useState<number | null>(null);
  const viewed = useShift(viewing);

  if (branches.length === 0 || branchId === null) {
    return <Result status="warning" title={t("pos.noBranch")} />;
  }

  const shift = current.data ?? null;
  const fail = (error: unknown) => message.error(errorMessage(error, t("common.error")));

  const historyColumns: ColumnsType<CashShift> = [
    { key: "opened", dataIndex: "opened_at", title: t("billing.openedAt"), render: (v: string) => formatDateTime(v) },
    { key: "closed", dataIndex: "closed_at", title: t("billing.closedAt"), render: (v: string | null) => (v ? formatDateTime(v) : <Tag color="green">{t("billing.statuses.open")}</Tag>) },
    { key: "opener", dataIndex: "opened_by", title: t("billing.user"), render: (u: CashShift["opened_by"]) => u?.name },
    { key: "cash", title: t("billing.methods.cash"), render: (_v, s) => formatMoney(s.totals.by_method.cash, unit) },
    { key: "card", title: t("billing.methods.card"), render: (_v, s) => formatMoney(s.totals.by_method.card, unit) },
    {
      key: "difference",
      dataIndex: "difference",
      title: t("billing.difference"),
      render: (v: number | null) => (v === null ? "—" : <Difference value={v} />),
    },
  ];

  return (
    <>
      <PageHeader
        title={t("billing.registerTitle")}
        extra={
          shift && can("shifts.operate") ? (
            <>
              <Button icon={<PlusCircleOutlined />} onClick={() => setMoving("in")}>
                {t("billing.movementTypes.in")}
              </Button>
              <Button icon={<MinusCircleOutlined />} onClick={() => setMoving("out")}>
                {t("billing.movementTypes.out")}
              </Button>
              <Button type="primary" danger icon={<LockOutlined />} onClick={() => setClosing(true)}>
                {t("billing.close")}
              </Button>
            </>
          ) : null
        }
      />
      {branches.length > 1 && (
        <Select
          value={branchId}
          onChange={choose}
          options={branches.map((b) => ({ value: b.id, label: `${b.name} (${b.code})` }))}
          style={{ width: 240, marginBottom: 16 }}
          aria-label={t("pos.branch")}
        />
      )}

      {can("shifts.operate") &&
        (current.isLoading ? (
          <Skeleton active />
        ) : shift ? (
          <Card title={<Flex gap={8} align="center">{t("billing.currentShift")} <Tag color="green">{t("billing.statuses.open")}</Tag></Flex>}>
            <ShiftDetails shift={shift} />
          </Card>
        ) : (
          <Card>
            <Result
              icon={<UnlockOutlined />}
              title={t("billing.closedTitle")}
              subTitle={t("billing.closedText")}
              extra={
                <Flex vertical gap={8} align="center">
                  <MoneyInput unit={unit} value={openingFloat} onChange={setOpeningFloat} style={{ width: 240 }} aria-label={t("billing.openingFloat")} placeholder={t("billing.openingFloat")} />
                  <Button
                    type="primary"
                    size="large"
                    loading={open.isPending}
                    onClick={() =>
                      open.mutate(
                        { branch_id: branchId, opening_float: openingFloat ?? 0 },
                        { onSuccess: () => message.success(t("billing.opened")), onError: fail },
                      )
                    }
                  >
                    {t("billing.open")}
                  </Button>
                </Flex>
              }
            />
          </Card>
        ))}

      {can("shifts.view") && (
        <Card title={t("billing.history")} style={{ marginTop: 16 }} styles={{ body: { padding: 0 } }}>
          <Table<CashShift>
            rowKey="id"
            size="small"
            columns={historyColumns}
            dataSource={history.data?.data ?? []}
            loading={history.isFetching}
            pagination={false}
            onRow={(s) => ({ onClick: () => setViewing(s.id), style: { cursor: "pointer" } })}
            scroll={{ x: "max-content" }}
          />
        </Card>
      )}

      <Modal
        open={closing}
        title={t("billing.close")}
        okText={t("billing.close")}
        okButtonProps={{ danger: true, disabled: counted === null }}
        cancelText={t("common.cancel")}
        confirmLoading={close.isPending}
        onCancel={() => setClosing(false)}
        onOk={() =>
          shift &&
          counted !== null &&
          close.mutate(
            { id: shift.id, counted_cash: counted },
            {
              onSuccess: (closed) => {
                setClosing(false);
                setCounted(null);
                setViewing(closed.id);
                message.success(t("billing.closed"));
              },
              onError: fail,
            },
          )
        }
        destroyOnHidden
      >
        {shift && (
          <Form layout="vertical">
            <Typography.Paragraph>
              {t("billing.expectedCash")}: <strong>{formatMoney(shift.totals.expected_cash, unit)} {t(`money.${unit}`)}</strong>
            </Typography.Paragraph>
            <Form.Item label={t("billing.countedCash")}>
              <MoneyInput unit={unit} size="large" value={counted} onChange={setCounted} autoFocus aria-label={t("billing.countedCash")} />
            </Form.Item>
            {counted !== null && counted !== shift.totals.expected_cash && (
              <Alert
                type={counted < shift.totals.expected_cash ? "error" : "warning"}
                showIcon
                title={`${counted < shift.totals.expected_cash ? t("billing.short") : t("billing.over")}: ${formatMoney(Math.abs(counted - shift.totals.expected_cash), unit)} ${t(`money.${unit}`)}`}
              />
            )}
          </Form>
        )}
      </Modal>

      <Modal
        open={moving !== null}
        title={moving ? t(`billing.movementTypes.${moving}`) : ""}
        okText={t("common.save")}
        cancelText={t("common.cancel")}
        okButtonProps={{ disabled: !moveAmount || moveReason.trim() === "" }}
        confirmLoading={movement.isPending}
        onCancel={() => setMoving(null)}
        onOk={() =>
          shift &&
          moving &&
          moveAmount &&
          movement.mutate(
            { id: shift.id, type: moving, amount: moveAmount, reason: moveReason.trim() },
            {
              onSuccess: () => {
                setMoving(null);
                setMoveAmount(null);
                setMoveReason("");
                message.success(t("common.saved"));
              },
              onError: fail,
            },
          )
        }
        destroyOnHidden
      >
        <Form layout="vertical">
          <Form.Item label={t("billing.amount")}>
            <MoneyInput unit={unit} value={moveAmount} onChange={setMoveAmount} autoFocus aria-label={t("billing.amount")} />
          </Form.Item>
          <Form.Item label={t("billing.reason")}>
            <Input value={moveReason} onChange={(e) => setMoveReason(e.target.value)} maxLength={255} aria-label={t("billing.reason")} />
          </Form.Item>
        </Form>
      </Modal>

      <Drawer open={viewing !== null} onClose={() => setViewing(null)} size="large" title={t("billing.shiftDetails")}>
        {viewed.data ? <ShiftDetails shift={viewed.data} /> : <Skeleton active />}
      </Drawer>
    </>
  );
}
