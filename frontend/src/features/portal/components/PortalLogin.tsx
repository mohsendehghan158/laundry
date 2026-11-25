"use client";

import { MobileOutlined } from "@ant-design/icons";
import { Alert, App, Button, Card, Flex, Form, Input, Typography } from "antd";
import { useRouter, useSearchParams } from "next/navigation";
import { useTranslations } from "next-intl";
import { useEffect, useState } from "react";
import { isApiError } from "@/shared/api/client";
import { applyFormErrors, errorMessage } from "@/shared/api/formErrors";
import { toLatinDigits, toPersianDigits } from "@/shared/lib/digits";
import { normalizeMobile, useCustomer, usePortalSettings, useSendOtp, useVerifyOtp } from "../api";
import { safePortalNext } from "../safePortalNext";
import type { OtpSent } from "../types";

const MOBILE_PATTERN = /^09\d{9}$/;

/** Seconds left until `until` (a Date.now() value), ticking once a second, never above `max`. */
function useSecondsLeft(until: number | null, max: number): number {
  const [now, setNow] = useState(() => Date.now());
  useEffect(() => {
    if (until === null) {
      return;
    }
    const timer = setInterval(() => setNow(Date.now()), 1000);
    return () => clearInterval(timer);
  }, [until]);
  // `now` is from the last tick, so the first render after a send would read one second high.
  return until === null ? 0 : Math.min(max, Math.max(0, Math.ceil((until - now) / 1000)));
}

/**
 * Customer sign-in: a mobile number, then the five-digit code texted to
 * it. A first-time number becomes a customer on the spot; the portal asks
 * for their name right after.
 */
export function PortalLogin() {
  const t = useTranslations("portal.login");
  const tc = useTranslations("common");
  const router = useRouter();
  const { message } = App.useApp();
  const next = safePortalNext(useSearchParams().get("next"));
  const settings = usePortalSettings();
  const customer = useCustomer();
  const sendOtp = useSendOtp();
  const verify = useVerifyOtp();
  const [mobileForm] = Form.useForm<{ mobile: string }>();
  const [codeForm] = Form.useForm<{ code: string }>();
  const [sent, setSent] = useState<OtpSent | null>(null);
  const [resendAt, setResendAt] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  // Bumped with a reset to empty the code boxes (a wrong code, a new code):
  // the one-time-code input keeps its own copy of the digits until it remounts.
  const [codeAttempt, setCodeAttempt] = useState(0);
  const secondsLeft = useSecondsLeft(resendAt, sent?.resend_in ?? 0);

  // Already signed in (a second tab, or back from the portal).
  useEffect(() => {
    if (customer.data) {
      router.replace(next);
    }
  }, [customer.data, next, router]);

  const requestCode = (mobile: string, again = false) => {
    setError(null);
    sendOtp.mutate(mobile, {
      onSuccess: (result) => {
        setSent(result);
        setResendAt(Date.now() + result.resend_in * 1000);
        if (again) {
          codeForm.resetFields();
          setCodeAttempt((attempt) => attempt + 1);
          message.success(t("resent"));
        }
      },
      onError: (failure) => {
        // A too-soon or too-many answer still leaves the earlier code usable.
        // Field errors go on the number form only while it is on screen.
        if (again || !applyFormErrors(mobileForm, failure)) {
          setError(errorMessage(failure, tc("error")));
        }
      },
    });
  };

  const submitCode = (code: string) => {
    if (!sent) {
      return;
    }
    setError(null);
    verify.mutate(
      { mobile: sent.mobile, code },
      {
        onSuccess: () => router.replace(next),
        onError: (failure) => {
          // Clear the boxes so the code can be typed again.
          if (isApiError(failure, 422) && (failure.code === "otp_wrong" || failure.code === "otp_expired")) {
            codeForm.resetFields();
            setCodeAttempt((attempt) => attempt + 1);
          }
          if (!applyFormErrors(codeForm, failure)) {
            setError(errorMessage(failure, tc("error")));
          }
        },
      },
    );
  };

  return (
    <Card style={{ width: "100%", maxWidth: 400 }} styles={{ body: { padding: 32 } }}>
      {settings.data && (
        <Typography.Text type="secondary" style={{ display: "block", marginBottom: 4 }}>
          {settings.data.organization_name}
        </Typography.Text>
      )}
      <Typography.Title level={3} style={{ marginTop: 0 }}>
        {t("title")}
      </Typography.Title>

      {error && <Alert type="error" title={error} showIcon style={{ marginBottom: 16 }} role="alert" />}

      {sent === null ? (
        <>
          <Typography.Paragraph type="secondary">{t("subtitle")}</Typography.Paragraph>
          <Form
            form={mobileForm}
            layout="vertical"
            requiredMark={false}
            size="large"
            onFinish={({ mobile }) => requestCode(normalizeMobile(mobile))}
          >
            <Form.Item
              name="mobile"
              label={t("mobile")}
              rules={[{ validator: async (_, value: string | undefined) => (MOBILE_PATTERN.test(normalizeMobile(value ?? "")) ? undefined : Promise.reject(new Error(t("mobileInvalid")))) }]}
            >
              <Input prefix={<MobileOutlined />} inputMode="tel" autoComplete="tel" autoFocus dir="ltr" placeholder="09121234567" />
            </Form.Item>
            <Button type="primary" htmlType="submit" block loading={sendOtp.isPending}>
              {t("sendCode")}
            </Button>
          </Form>
        </>
      ) : (
        <>
          <Typography.Paragraph type="secondary">{t("codeSentTo", { mobile: toPersianDigits(sent.mobile) })}</Typography.Paragraph>
          <Form key={codeAttempt} form={codeForm} layout="vertical" requiredMark={false} size="large" onFinish={({ code }) => submitCode(code)}>
            <Form.Item
              name="code"
              label={t("code")}
              rules={[{ validator: async (_, value: string | undefined) => (/^\d{5}$/.test(toLatinDigits(value ?? "")) ? undefined : Promise.reject(new Error(t("codeInvalid")))) }]}
            >
              <Input.OTP
                length={5}
                autoFocus
                dir="ltr"
                inputMode="numeric"
                formatter={(value) => toLatinDigits(value).replace(/\D/g, "")}
                // The last digit sends the form, as phones fill the code in one go.
                onChange={(value) => {
                  if (/^\d{5}$/.test(value)) {
                    submitCode(value);
                  }
                }}
              />
            </Form.Item>
            <Button type="primary" htmlType="submit" block loading={verify.isPending}>
              {t("verify")}
            </Button>
          </Form>
          <Flex justify="space-between" align="center" wrap gap={8} style={{ marginTop: 16 }}>
            <Button
              type="link"
              style={{ paddingInline: 0 }}
              onClick={() => {
                setSent(null);
                setResendAt(null);
                setError(null);
              }}
            >
              {t("changeMobile")}
            </Button>
            {secondsLeft > 0 ? (
              <Typography.Text type="secondary">{t("resendIn", { seconds: secondsLeft })}</Typography.Text>
            ) : (
              <Button type="link" style={{ paddingInline: 0 }} loading={sendOtp.isPending} onClick={() => requestCode(sent.mobile, true)}>
                {t("resend")}
              </Button>
            )}
          </Flex>
        </>
      )}
    </Card>
  );
}
