"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/shared/api/client";
import type { Claim, ClaimKind, ClaimStatus, PiecePhoto, Shipment, TagResult, Transfer, TransferStatus, Vendor, Wrapped } from "@/shared/api/types";

const keys = {
  transfers: (status: TransferStatus | "active") => ["transfers", status] as const,
  transfer: (id: number) => ["transfers", "detail", id] as const,
  shipments: (status: "sent" | "closed") => ["shipments", status] as const,
  vendors: ["vendors"] as const,
  claims: (status: ClaimStatus) => ["claims", status] as const,
};

type WithResults<T> = { data: T; results: TagResult[] };

export function useTransfers(status: TransferStatus | "active") {
  return useQuery({
    queryKey: keys.transfers(status),
    queryFn: async () => (await api.get<Wrapped<Transfer[]>>("/transfers", { status: status === "active" ? undefined : status })).data,
  });
}

export function useTransfer(id: number | null) {
  return useQuery({
    queryKey: keys.transfer(id ?? 0),
    queryFn: async () => (await api.get<Wrapped<Transfer>>(`/transfers/${id}`)).data,
    enabled: id !== null,
  });
}

/** Every change to a transfer answers with the transfer; keep it and refresh the lists. */
function useTransferMutation<I, R extends Transfer | WithResults<Transfer>>(request: (input: I) => Promise<R>) {
  const queryClient = useQueryClient();
  return useMutation<R, Error, I>({
    mutationFn: request,
    onSuccess: (result) => {
      const value: Transfer | WithResults<Transfer> = result;
      const transfer: Transfer = "results" in value ? value.data : value;
      queryClient.setQueryData(keys.transfer(transfer.id), transfer);
      queryClient.invalidateQueries({ queryKey: ["transfers"], exact: false, predicate: (q) => q.queryKey[1] !== "detail" });
      queryClient.invalidateQueries({ queryKey: ["pieces"] });
    },
  });
}

export function useCreateTransfer() {
  return useTransferMutation(async (input: { from_branch_id: number; to_branch_id: number; note?: string | null }) => (await api.post<Wrapped<Transfer>>("/transfers", input)).data);
}

export function useTransferPieces() {
  return useTransferMutation(async ({ id, tags, mode }: { id: number; tags: string[]; mode: "add" | "receive" }) =>
    api.post<WithResults<Transfer>>(`/transfers/${id}/${mode === "add" ? "pieces" : "receive"}`, { tags }),
  );
}

export function useRemoveTransferPiece() {
  return useTransferMutation(async ({ id, pieceId }: { id: number; pieceId: number }) => (await api.delete<Wrapped<Transfer>>(`/transfers/${id}/pieces/${pieceId}`)).data);
}

export function useSendTransfer() {
  return useTransferMutation(async ({ id, bags }: { id: number; bags: number }) => (await api.post<Wrapped<Transfer>>(`/transfers/${id}/send`, { bags })).data);
}

export function useCloseTransfer() {
  return useTransferMutation(async ({ id, bags, all }: { id: number; bags: number | null; all: boolean }) => (await api.post<Wrapped<Transfer>>(`/transfers/${id}/close`, { bags, all })).data);
}

export function useCancelTransfer() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api.delete(`/transfers/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["transfers"] }),
  });
}

export function useVendors(enabled = true) {
  return useQuery({ queryKey: keys.vendors, queryFn: async () => (await api.get<Wrapped<Vendor[]>>("/vendors")).data, enabled });
}

export type VendorInput = { name: string; phone?: string | null; services?: string | null; is_active: boolean };

export function useSaveVendor() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, input }: { id?: number; input: VendorInput }) =>
      (id ? await api.put<Wrapped<Vendor>>(`/vendors/${id}`, input) : await api.post<Wrapped<Vendor>>("/vendors", input)).data,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: keys.vendors }),
  });
}

export function useDeleteVendor() {
  const queryClient = useQueryClient();
  return useMutation({ mutationFn: (id: number) => api.delete(`/vendors/${id}`), onSuccess: () => queryClient.invalidateQueries({ queryKey: keys.vendors }) });
}

export function useShipments(status: "sent" | "closed") {
  return useQuery({ queryKey: keys.shipments(status), queryFn: async () => (await api.get<Wrapped<Shipment[]>>("/shipments", { status })).data });
}

export type ShipmentInput = { vendor_id: number; branch_id: number; tags: string[]; expected_at?: string | null; cost?: number | null; note?: string | null };

export function useCreateShipment() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: ShipmentInput) => (await api.post<Wrapped<Shipment>>("/shipments", input)).data,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["shipments"] });
      queryClient.invalidateQueries({ queryKey: ["pieces"] });
    },
  });
}

export function useReceiveShipment() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, tags }: { id: number; tags: string[] }) => api.post<WithResults<Shipment>>(`/shipments/${id}/receive`, { tags }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["shipments"] });
      queryClient.invalidateQueries({ queryKey: ["pieces"] });
    },
  });
}

export function useClaims(status: ClaimStatus) {
  return useQuery({ queryKey: keys.claims(status), queryFn: async () => (await api.get<Wrapped<Claim[]>>("/claims", { status })).data });
}

export function useOpenClaim() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: { piece_id: number; kind: ClaimKind; description: string }) => (await api.post<Wrapped<Claim>>("/claims", input)).data,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["claims"] });
      queryClient.invalidateQueries({ queryKey: ["pieces"] });
    },
  });
}

export function useSettleClaim() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, action, resolution, compensation }: { id: number; action: "resolve" | "reject"; resolution: string; compensation?: number | null }) =>
      (await api.post<Wrapped<Claim>>(`/claims/${id}/${action}`, { resolution, compensation })).data,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["claims"] });
      queryClient.invalidateQueries({ queryKey: ["pieces"] });
    },
  });
}

export function useUploadPhoto() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ pieceId, file }: { pieceId: number; file: File }) => {
      const form = new FormData();
      form.append("photo", file);
      return (await api.post<Wrapped<PiecePhoto>>(`/pieces/${pieceId}/photos`, form)).data;
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["pieces"] }),
  });
}

export function useDeletePhoto() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (photo: PiecePhoto) => api.delete(photo.url.replace(/^\/api\/v1/, "")),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["pieces"] }),
  });
}

export function useTransferDestinations() {
  return useQuery({
    queryKey: ["transfer-destinations"],
    queryFn: async () => (await api.get<{ data: Array<{ id: number; name: string; type: string }> }>("/transfer-destinations")).data,
    staleTime: 5 * 60_000,
  });
}
