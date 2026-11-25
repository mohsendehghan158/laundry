"use client";

import { Alert, App, Form, Input, Modal, Radio, Typography } from "antd";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { isApiError } from "@/shared/api/client";
import { applyFormErrors, errorMessage } from "@/shared/api/formErrors";
import type { ClaimKind } from "@/shared/api/types";
import { usePiece } from "../api";
import { useOpenClaim } from "../movementApi";
import { normalizeTag } from "../tags";

/**
 * Opens a damage or loss claim. Given a piece it asks only what happened;
 * otherwise the piece's tag is scanned first.
 */
export function ClaimDialog({ open, onClose, piece }: { open: boolean; onClose: () => void; piece?: { id: number; tag_code: string; item_name: string } }) {
  const t = useTranslations();
  const { message } = App.useApp();
  const [form] = Form.useForm<{ kind: ClaimKind; description: string }>();
  const [tag, setTag] = useState<string | null>(null);
  const found = usePiece(piece ? null : tag);
  const target = piece ?? (found.data ? { id: found.data.id, tag_code: found.data.tag_code, item_name: found.data.item_name } : null);
  const openClaim = useOpenClaim();

  return (
    <Modal
      open={open}
      title={t("claims.new")}
      okText={t("claims.open")}
      okButtonProps={{ disabled: !target }}
      cancelText={t("common.cancel")}
      onCancel={onClose}
      onOk={() => form.submit()}
      confirmLoading={openClaim.isPending}
      destroyOnHidden
      afterClose={() => setTag(null)}
    >
      <Form
        form={form}
        layout="vertical"
        preserve={false}
        initialValues={{ kind: "damaged" }}
        onFinish={(values) =>
          target &&
          openClaim.mutate(
            { piece_id: target.id, ...values },
            {
              onSuccess: (claim) => {
                message.success(t("claims.opened", { number: claim.number }));
                onClose();
              },
              onError: (error) => !applyFormErrors(form, error) && message.error(errorMessage(error, t("common.error"))),
            },
          )
        }
      >
        {!piece && (
          <Form.Item label={t("claims.fields.piece")} required>
            <Input.Search
              aria-label={t("claims.fields.piece")}
              placeholder={t("station.scanPlaceholder")}
              onSearch={(value) => setTag(normalizeTag(value) || null)}
              enterButton={t("common.search")}
              className="ltr"
            />
            {found.isError && <Alert style={{ marginTop: 8 }} type="error" showIcon title={isApiError(found.error, 404) ? t("pieceLookup.notFound", { tag: tag ?? "" }) : errorMessage(found.error, t("common.error"))} />}
          </Form.Item>
        )}
        {target && (
          <Typography.Paragraph strong>
            <span className="ltr">{target.tag_code}</span> · {target.item_name}
          </Typography.Paragraph>
        )}
        <Form.Item name="kind" label={t("claims.fields.kind")}>
          <Radio.Group optionType="button" options={(["damaged", "lost"] as const).map((kind) => ({ value: kind, label: t(`claims.kinds.${kind}`) }))} />
        </Form.Item>
        <Form.Item name="description" label={t("claims.fields.description")} rules={[{ required: true, message: t("common.required") }]}>
          <Input.TextArea rows={3} maxLength={1000} />
        </Form.Item>
      </Form>
    </Modal>
  );
}
