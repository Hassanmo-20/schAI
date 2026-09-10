import { apiClient, USE_MOCK_DATA } from './apiClient';
import { Batch } from '../types';
import { MOCK_BATCHES } from '../data/mockData';

/**
 * Registration requires a real `batch_id`, so the picker is populated from the
 * API rather than a hardcoded list of names.
 */
export const batchService = {
  async getBatches(): Promise<Batch[]> {
    if (USE_MOCK_DATA) {
      await new Promise((resolve) => setTimeout(resolve, 150));
      return MOCK_BATCHES;
    }

    const response = await apiClient.get<{ data: any[] }>('/batches');
    return (response?.data ?? []).map((b) => ({
      id: String(b.id),
      name: b.name,
      department: b.department ?? undefined,
    }));
  },
};
