/** The one error shape of the API: { message, code, errors? }. */
export interface ApiErrorBody {
  message: string;
  code: string;
  errors?: Record<string, string[]>;
}

export class ApiError extends Error {
  readonly status: number;
  readonly code: string;
  readonly errors: Record<string, string[]>;

  constructor(status: number, body: Partial<ApiErrorBody>) {
    super(body.message || "خطایی رخ داد.");
    this.name = "ApiError";
    this.status = status;
    this.code = body.code ?? (status === 0 ? "network_error" : "http_error");
    this.errors = body.errors ?? {};
  }

  get isValidation(): boolean {
    return this.status === 422;
  }
}

export function isApiError(error: unknown): error is ApiError {
  return error instanceof ApiError;
}

/** Message to show a person for any thrown value. */
export function errorMessage(error: unknown): string {
  if (isApiError(error)) return error.message;
  return "ارتباط با سرور برقرار نشد. اتصال اینترنت را بررسی کنید.";
}
