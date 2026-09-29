export type QrCode = {
  id: string;
  code: string;
  destinationUrl: string | null;
  active: boolean;
  batchId?: string;
  createdAt?: string;
  updatedAt?: string;
  batch?: { id: string; startCode: string; endCode: string };
};

export type Batch = {
  id: string;
  startCode: string;
  endCode: string;
  quantity: number;
  createdAt: string;
  qrCodes: QrCode[];
};

export type BatchPage = { items: Batch[]; total: number; page: number; pageSize: number };

export type User = {
  id: string;
  username: string;
  codePrefix: string;
  role: "admin" | "vendedor" | "manutencao";
  active: boolean;
  createdAt: string;
  updatedAt: string;
};
