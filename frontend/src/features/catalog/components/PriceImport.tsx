"use client";

import { DownloadOutlined, UploadOutlined } from "@ant-design/icons";
import { Alert, App, Button, Modal, Table, Tag, Tooltip, Typography, Upload } from "antd";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { errorMessage } from "@/shared/api/formErrors";
import type { PriceImport as PriceImportResult } from "@/shared/api/types";
import { useCurrencyUnit } from "@/shared/hooks/useCurrencyUnit";
import { formatNumber } from "@/shared/lib/digits";
import { formatMoney } from "@/shared/lib/money";
import { useExportPrices, useImportPrices } from "../api";

type Change = PriceImportResult["changes"][number];

/**
 * Download the price list as Excel and upload an edited copy: the upload
 * is compared first, and saved only when the user confirms the changes.
 */
export function PriceImport({ editable, blocked }: { editable: boolean; blocked: boolean }) {
  const t = useTranslations("catalog.sheet");
  const tc = useTranslations("common");
  const tm = useTranslations("money");
  const { message } = App.useApp();
  const unit = useCurrencyUnit();
  const exporter = useExportPrices();
  const importer = useImportPrices();
  const [file, setFile] = useState<File | null>(null);
  const [result, setResult] = useState<PriceImportResult | null>(null);

  const close = () => {
    setFile(null);
    setResult(null);
  };

  const preview = (chosen: File) => {
    setFile(chosen);
    importer.mutate(
      { file: chosen, apply: false },
      {
        onSuccess: setResult,
        onError: (error) => {
          close();
          message.error(errorMessage(error, tc("error")));
        },
      },
    );
  };

  const apply = () =>
    file &&
    importer.mutate(
      { file, apply: true },
      {
        onSuccess: (applied) => {
          if (applied.applied) {
            message.success(t("applied", { count: applied.changes.length }));
            close();
          } else {
            // Prices changed meanwhile, or the sheet no longer passes: show the fresh comparison.
            setResult(applied);
          }
        },
        onError: (error) => message.error(errorMessage(error, tc("error"))),
      },
    );

  const price = (value: number | null) => (value === null ? <Typography.Text type="secondary">{t("notOffered")}</Typography.Text> : formatMoney(value, unit));
  const problems = result ? result.errors.length : 0;

  return (
    <>
      <Button icon={<DownloadOutlined />} loading={exporter.isPending} onClick={() => exporter.mutate(undefined, { onError: (error) => message.error(errorMessage(error, tc("error"))) })}>
        {t("download")}
      </Button>
      {editable && (
        <Upload accept=".xlsx" showUploadList={false} disabled={blocked} beforeUpload={(chosen) => (preview(chosen), false)}>
          <Tooltip title={blocked ? t("unsavedFirst") : undefined}>
            <Button icon={<UploadOutlined />} disabled={blocked} loading={importer.isPending && !result}>
              {t("upload")}
            </Button>
          </Tooltip>
        </Upload>
      )}
      <Modal
        open={result !== null}
        width={760}
        title={t("previewTitle", { file: file?.name ?? "" })}
        onCancel={close}
        okText={t("apply", { count: result?.changes.length ?? 0 })}
        okButtonProps={{ disabled: !result || problems > 0 || result.changes.length === 0 }}
        cancelText={tc("cancel")}
        confirmLoading={importer.isPending}
        onOk={apply}
        destroyOnHidden
      >
        {result && (
          <>
            {problems > 0 && (
              <Alert
                type="error"
                showIcon
                style={{ marginBottom: 12 }}
                title={t("errorsTitle", { count: problems })}
                description={
                  <ul style={{ margin: 0, paddingInlineStart: 20 }}>
                    {result.errors.map((error, index) => (
                      <li key={index}>{error.row === null ? error.message : `${t("row", { row: formatNumber(error.row) })}: ${error.message}`}</li>
                    ))}
                  </ul>
                }
              />
            )}
            {result.warnings.map((warning, index) => (
              <Alert key={index} type="warning" showIcon style={{ marginBottom: 12 }} title={warning.message} />
            ))}
            {result.changes.length === 0 ? (
              problems === 0 && <Alert type="info" showIcon title={t("noChanges")} />
            ) : (
              <Table<Change>
                size="small"
                rowKey={(change) => `${change.item_id}:${change.service_type_id}`}
                dataSource={result.changes}
                pagination={result.changes.length > 50 ? { pageSize: 50, showSizeChanger: false } : false}
                scroll={{ y: 360 }}
                columns={[
                  { key: "item", dataIndex: "item", title: t("columns.item") },
                  { key: "service", dataIndex: "service", title: t("columns.service") },
                  { key: "old", dataIndex: "old", title: t("columns.old", { unit: tm(unit) }), render: price },
                  {
                    key: "new",
                    title: t("columns.new", { unit: tm(unit) }),
                    render: (_value, change) => (
                      <>
                        {price(change.new)}
                        {change.old === null && <Tag color="green" style={{ marginInlineStart: 8 }}>{t("added")}</Tag>}
                        {change.new === null && <Tag color="red" style={{ marginInlineStart: 8 }}>{t("removed")}</Tag>}
                      </>
                    ),
                  },
                ]}
              />
            )}
          </>
        )}
      </Modal>
    </>
  );
}
