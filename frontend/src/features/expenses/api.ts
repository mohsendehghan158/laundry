"use client";

import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/shared/api/client";
import type { Expense, ExpenseCategory, ExpenseList, SaveExpenseInput, Wrapped } from "@/shared/api/types";

export const expenseKeys = {
  list: (filters: object) => ["expenses", "list", filters] as const,
  categories: ["expense-categories"] as const,
};

export type ExpenseFilters = {
  q?: string;
  branch_id?: number | null;
  category_id?: number | null;
  from?: string | null;
  to?: string | null;
  page?: number;
  per_page?: number;
};

export function useExpenses(filters: ExpenseFilters) {
  return useQuery({
    queryKey: expenseKeys.list(filters),
    queryFn: () =>
      api.get<ExpenseList>("/expenses", {
        "filter[q]": filters.q || undefined,
        "filter[branch_id]": filters.branch_id,
        "filter[category_id]": filters.category_id,
        "filter[from]": filters.from,
        "filter[to]": filters.to,
        page: filters.page,
        per_page: filters.per_page,
      }),
    placeholderData: keepPreviousData,
  });
}

export function useExpenseCategories(enabled = true) {
  return useQuery({
    queryKey: expenseKeys.categories,
    queryFn: async () => (await api.get<Wrapped<ExpenseCategory[]>>("/expense-categories")).data,
    enabled,
  });
}

export function useSaveExpense() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, input }: { id?: number; input: Partial<SaveExpenseInput> }) =>
      (id ? await api.put<Wrapped<Expense>>(`/expenses/${id}`, input) : await api.post<Wrapped<Expense>>("/expenses", input)).data,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["expenses"] });
      queryClient.invalidateQueries({ queryKey: ["cash-shifts"] });
    },
  });
}

export function useDeleteExpense() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api.delete(`/expenses/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["expenses"] });
      queryClient.invalidateQueries({ queryKey: ["cash-shifts"] });
    },
  });
}

export function useSaveExpenseCategory() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, input }: { id?: number; input: { name?: string; is_active?: boolean } }) =>
      (id ? await api.put<Wrapped<ExpenseCategory>>(`/expense-categories/${id}`, input) : await api.post<Wrapped<ExpenseCategory>>("/expense-categories", input)).data,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: expenseKeys.categories }),
  });
}

export function useDeleteExpenseCategory() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api.delete(`/expense-categories/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: expenseKeys.categories }),
  });
}
