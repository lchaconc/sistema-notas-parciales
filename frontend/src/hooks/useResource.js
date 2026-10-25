import { useCallback, useEffect, useState } from 'react';

export function useResource(loader, dependencies) {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const reload = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const result = await loader();
      setData(result);
      return result;
    } catch (cause) {
      setError(cause.message);
      return null;
    } finally {
      setLoading(false);
    }
  }, dependencies);
  useEffect(() => { reload(); }, [reload]);
  return { data, loading, error, reload, setData };
}
