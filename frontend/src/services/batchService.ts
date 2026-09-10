import { apiClient, USE_MOCK_DATA } from './apiClient';
import { Batch, Choice, RegistrationOptions } from '../types';
import { MOCK_BATCHES, MOCK_REGISTRATION_OPTIONS } from '../data/mockData';

/** `batch_years` arrives as bare strings; the other lists already carry labels. */
function toChoices(raw: unknown): Choice[] {
  if (!Array.isArray(raw)) return [];
  return raw.map((entry) =>
    typeof entry === 'string'
      ? { value: entry, label: entry }
      : { value: String(entry?.value ?? ''), label: String(entry?.label ?? entry?.value ?? '') }
  );
}

export const batchService = {
  /**
   * The choices the registration form offers.
   *
   * Served from the backend enums rather than the batches table, so the form
   * is correct even before a group has any members — and so "which batch years
   * exist" stays a backend decision the UI never has to duplicate.
   */
  async getRegistrationOptions(): Promise<RegistrationOptions> {
    if (USE_MOCK_DATA) {
      await new Promise((resolve) => setTimeout(resolve, 150));
      return MOCK_REGISTRATION_OPTIONS;
    }

    const response = await apiClient.get<{ data: Record<string, unknown> }>('/registration-options');
    const data = response?.data ?? {};

    return {
      batchYears: toChoices(data.batch_years),
      departments: toChoices(data.departments),
      roles: toChoices(data.roles),
    };
  },

  /**
   * Groups that already exist. Informational only — registration submits the
   * (batch year, department) pair, never one of these ids.
   */
  async getBatches(): Promise<Batch[]> {
    if (USE_MOCK_DATA) {
      await new Promise((resolve) => setTimeout(resolve, 150));
      return MOCK_BATCHES;
    }

    const response = await apiClient.get<{ data: any[] }>('/batches');
    return (response?.data ?? []).map((b) => ({
      id: String(b.id),
      name: b.name,
      batchYear: b.batch_year ?? undefined,
      department: b.department ?? undefined,
    }));
  },
};
