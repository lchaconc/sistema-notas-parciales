const API_ROOT = import.meta.env.VITE_API_URL || '/api';

export class ApiError extends Error {
  constructor(message, status = 0) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
  }
}

export async function request(path, { method = 'GET', body, token, onUnauthorized, binary = false } = {}) {
  const headers = {};
  if (token) headers.Authorization = `Bearer ${token}`;
  if (body !== undefined) headers['Content-Type'] = 'application/json';

  let response;
  try {
    response = await fetch(`${API_ROOT}${path}`, {
      method,
      headers,
      ...(body !== undefined ? { body: JSON.stringify(body) } : {}),
    });
  } catch {
    throw new ApiError('No se pudo conectar con el servidor. Revise su conexión.');
  }

  if (!response.ok) {
    let detail;
    try { detail = (await response.json()).error; } catch { /* La API no devolvió JSON. */ }
    if (response.status === 401 && onUnauthorized) onUnauthorized();
    throw new ApiError(detail || `La operación no se completó (${response.status}).`, response.status);
  }

  if (binary) return response.blob();
  try {
    return (await response.json()).data;
  } catch {
    throw new ApiError('El servidor devolvió una respuesta inesperada.');
  }
}

export function createApi(token, onUnauthorized) {
  const options = { token, onUnauthorized };
  return {
    get: (path) => request(path, options),
    post: (path, body) => request(path, { ...options, method: 'POST', body }),
    put: (path, body) => request(path, { ...options, method: 'PUT', body }),
    delete: (path) => request(path, { ...options, method: 'DELETE' }),
    download: (path) => request(path, { ...options, binary: true }),
  };
}
