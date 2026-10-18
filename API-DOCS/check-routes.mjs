import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const routes = readFileSync(new URL('../Backend/app/Config/Routes.php', import.meta.url), 'utf8');
const specification = readFileSync(new URL('./openapi.yaml', import.meta.url), 'utf8');

const normalize = (path) => path.replaceAll(/\(\:num\)|\{[^/}]+\}/g, '{id}');
const implemented = new Set();
let prefix = '';
for (const line of routes.split(/\r?\n/)) {
  const group = line.match(/\$routes->group\('([^']+)'/);
  if (group) prefix = `${group[1]}/`;
  if (line.trim() === '});') prefix = '';
  const route = line.match(/\$routes->(get|post|put|delete)\('([^']+)'/);
  if (route) implemented.add(`${route[1].toUpperCase()} ${normalize(`/${prefix}${route[2]}`)}`);
}

const documented = new Set();
let path = '';
for (const line of specification.split(/\r?\n/)) {
  const route = line.match(/^  (\/[^:]+):$/);
  if (route) path = route[1];
  if (line === 'components:') path = '';
  const method = line.match(/^    (get|post|put|delete):$/);
  if (path && method) documented.add(`${method[1].toUpperCase()} ${normalize(`/api${path}`)}`);
}

const missing = [...implemented].filter((route) => !documented.has(route));
const extra = [...documented].filter((route) => !implemented.has(route));
assert.deepEqual(missing, [], `Rutas del backend sin documentar: ${missing.join(', ')}`);
assert.deepEqual(extra, [], `Operaciones documentadas sin ruta: ${extra.join(', ')}`);
console.log(`${documented.size} operaciones OpenAPI corresponden a las rutas del backend.`);
