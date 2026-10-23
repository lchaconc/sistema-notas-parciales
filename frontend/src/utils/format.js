export function decimal(value) {
  if (value === null || value === undefined) return 'Sin calificar';
  const normalized = String(value).replace('.', ',');
  const [integer, fraction = ''] = normalized.split(',');
  return `${integer},${fraction.padEnd(2, '0')}`;
}

export function percent(value) {
  return `${decimal(value)} %`;
}

export function fullName(person) {
  return [person.nombre, person.primer_apellido, person.segundo_apellido].filter(Boolean).join(' ');
}

export function courseLabel(course) {
  return `${course.codigo} · Grupo ${course.numero_grupo} · ${course.cuatrimestre_codigo}`;
}

export function decimalInput(value) {
  return String(value).trim().replace(',', '.');
}

export function validDecimal(value, min, max) {
  const valueText = decimalInput(value);
  if (!/^\d+(?:\.\d{1,2})?$/.test(valueText)) return false;
  const amount = Number(valueText);
  return amount >= min && amount <= max;
}

export function wordCount(value) {
  return value.trim() ? value.trim().split(/\s+/u).length : 0;
}
