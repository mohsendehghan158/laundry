"use client";

import { Alert, App, Button, Checkbox, Flex, Input, List, Modal, Skeleton, Space, Tag, Typography } from "antd";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { errorMessage } from "@/shared/api/formErrors";
import type { AssemblyState, PieceStatus } from "@/shared/api/types";
import { useAssembly, useCompleteAssembly } from "../api";
import { normalizeTag } from "../tags";
import { RackPicker, type RackChoice } from "./RackPicker";

function Body({ state, onDone }: { state: AssemblyState; onDone: () => void }) {
  const t = useTranslations();
  const { message } = App.useApp();
  const complete = useCompleteAssembly();
  const pickable = state.pieces.filter((p) => ["received", "in_process"].includes(p.status) && !p.blocker && p.flag !== "lost");
  const [ticked, setTicked] = useState<number[]>([]);
  const [scanText, setScanText] = useState("");
  const [rack, setRack] = useState<RackChoice>({ slotId: state.suggestion?.id ?? null, location: "" });

  const gathered = state.counts.assembled + ticked.length;
  const waiting = state.counts.waiting - ticked.length;
  const done = waiting === 0 && gathered > 0;

  const submit = (partial: boolean) =>
    complete.mutate(
      {
        orderId: state.order.id,
        input: { piece_ids: ticked, rack_slot_id: rack.slotId, rack_location: rack.slotId ? null : rack.location.trim() || null, partial },
      },
      {
        onSuccess: (result) => {
          message.success(t(result.order.status === "ready" ? "assembly.readyDone" : "assembly.partialDone", { ticket: result.order.ticket_number, rack: result.order.rack_location ?? "—" }));
          onDone();
        },
        onError: (error) => message.error(errorMessage(error, t("common.error"))),
      },
    );

  return (
    <Flex vertical gap={12}>
      {state.scan_required ? (
        <Alert type="info" showIcon title={t("assembly.scanRequired")} />
      ) : (
        <Flex gap={8}>
          <Input
            aria-label={t("assembly.tickByScan")}
            placeholder={t("assembly.tickByScan")}
            value={scanText}
            onChange={(e) => setScanText(e.target.value)}
            onPressEnter={() => {
              const piece = pickable.find((p) => p.tag_code === normalizeTag(scanText));
              if (piece) setTicked((current) => (current.includes(piece.id) ? current : [...current, piece.id]));
              else message.warning(t("assembly.notInOrder"));
              setScanText("");
            }}
            className="ltr"
          />
          <Button onClick={() => setTicked(pickable.map((p) => p.id))} disabled={pickable.length === 0}>
            {t("assembly.tickAll")}
          </Button>
        </Flex>
      )}
      <List
        size="small"
        bordered
        dataSource={state.pieces}
        renderItem={(piece) => {
          const done = !["received", "in_process"].includes(piece.status);
          const canTick = !state.scan_required && pickable.some((p) => p.id === piece.id);
          return (
            <List.Item>
              <Flex justify="space-between" align="center" gap={8} style={{ width: "100%" }}>
                <Checkbox
                  checked={done || ticked.includes(piece.id)}
                  disabled={done || !canTick}
                  onChange={(e) => setTicked((current) => (e.target.checked ? [...current, piece.id] : current.filter((id) => id !== piece.id)))}
                  aria-label={piece.tag_code}
                >
                  <Space size={4} wrap>
                    <Typography.Text className="ltr">{piece.tag_code}</Typography.Text>
                    <span>{piece.item_name}</span>
                    {piece.color_name && <Typography.Text type="secondary">{piece.color_name}</Typography.Text>}
                  </Space>
                </Checkbox>
                {piece.blocker ? (
                  <Typography.Text type="warning">{piece.blocker}</Typography.Text>
                ) : (
                  <Tag>{t(`pieces.statuses.${piece.status as PieceStatus}`)}</Tag>
                )}
              </Flex>
            </List.Item>
          );
        }}
      />
      <Typography.Text strong>{t("assembly.progress", { gathered, total: state.counts.total - state.counts.lost - state.counts.ready })}</Typography.Text>
      <RackPicker branchId={state.order.branch.id} suggestion={state.suggestion} value={rack} onChange={setRack} />
      <Flex justify="end" gap={8} wrap>
        {!done && gathered > 0 && (
          <Button loading={complete.isPending} onClick={() => submit(true)}>
            {t("assembly.partial")}
          </Button>
        )}
        <Button type="primary" disabled={!done} loading={complete.isPending} onClick={() => submit(false)}>
          {t("assembly.complete")}
        </Button>
      </Flex>
    </Flex>
  );
}

/**
 * Marking an order ready from its page: tick off (or scan) each piece,
 * choose the rack slot, and the order becomes ready; or rack what is here
 * while the rest is still being worked on.
 */
export function AssemblyDialog({ orderId, open, onClose }: { orderId: number; open: boolean; onClose: () => void }) {
  const t = useTranslations();
  const state = useAssembly(open ? orderId : null);

  return (
    <Modal open={open} title={t("assembly.dialogTitle")} onCancel={onClose} footer={null} width={640} destroyOnHidden>
      {state.isLoading || !state.data ? <Skeleton active /> : <Body state={state.data} onDone={onClose} />}
    </Modal>
  );
}
