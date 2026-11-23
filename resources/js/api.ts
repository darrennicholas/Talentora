import axios, { type AxiosInstance, type AxiosRequestConfig } from 'axios';

export interface ApiEnvelope<T> {
  data: T;
  message?: string;
}

export interface PaginationMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}

export interface PaginatedApiResponse<T> extends ApiEnvelope<T[]> {
  meta: PaginationMeta;
}

export interface ApiErrorResponse {
  message: string;
  errors?: Record<string, string[]>;
  code?: string;
}

export const api: AxiosInstance = axios.create({
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json' },
});

export function getResource<T>(url: string, config?: AxiosRequestConfig) {
  return api.get<ApiEnvelope<T>>(url, config);
}

export function getPage<T>(url: string, config?: AxiosRequestConfig) {
  return api.get<PaginatedApiResponse<T>>(url, config);
}
