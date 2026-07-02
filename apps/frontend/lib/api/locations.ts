import { apiFetch } from "./client";

export interface State {
  id: number;
  name: string;
  code: string;
}

export interface Municipality {
  id: number;
  name: string;
  state_id: number;
}

export interface Parish {
  id: number;
  name: string;
  municipality_id: number;
}

export async function getStates(): Promise<State[]> {
  const res = await apiFetch<{ data: State[] }>("/api/locations/states");
  return res.data;
}

export async function getMunicipalities(stateId: number): Promise<Municipality[]> {
  const res = await apiFetch<{ data: Municipality[] }>(
    `/api/locations/municipalities?state_id=${stateId}`,
  );
  return res.data;
}

export async function getParishes(municipalityId: number): Promise<Parish[]> {
  const res = await apiFetch<{ data: Parish[] }>(
    `/api/locations/parishes?municipality_id=${municipalityId}`,
  );
  return res.data;
}
