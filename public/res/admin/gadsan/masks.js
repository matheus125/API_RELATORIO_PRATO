(() => {
  'use strict';
  const digits = value => value.replace(/\D/g, '');
  const subscriber = number => {
    const split = number.length > 8 ? 5 : 4;
    return number.length > split ? `${number.slice(0, split)}-${number.slice(split)}` : number;
  };
  function cpf(value) {
    const number = digits(value);
    // Keep oversized imported values visible for correction; never truncate documents.
    if (number.length > 11) return value;
    return number.replace(/^(\d{3})(\d)/, '$1.$2')
      .replace(/^(\d{3}\.\d{3})(\d)/, '$1.$2')
      .replace(/^(\d{3}\.\d{3}\.\d{3})(\d)/, '$1-$2');
  }
  function phone(value) {
    const number = digits(value);
    const international = /^\s*\+55/.test(value) || number.length >= 12 && number.length <= 13 && number.startsWith('55');
    if (number.length > 13 || number.length > 11 && !number.startsWith('55')) return value;
    if (international) {
      const national = number.slice(2);
      if (!national) return '+55';
      if (national.length <= 2) return `+55 (${national}`;
      return `+55 (${national.slice(0, 2)}) ${subscriber(national.slice(2))}`;
    }
    if (/^\s*\+/.test(value)) return value; // Do not reinterpret another country code.
    if (number.length <= 9) return subscriber(number);
    return `(${number.slice(0, 2)}) ${subscriber(number.slice(2))}`;
  }
  const masks = {cpf, phone};
  if (typeof module === 'object' && module.exports) module.exports = masks;
  if (typeof document === 'undefined') return;
  const scope = document.querySelector('.gadsan-module');
  if (!scope) return;
  function apply(input, keepCursor = false) {
    const format = masks[input.dataset.gadsanMask];
    if (!format) return;
    const original = input.value;
    const position = input.selectionStart ?? original.length;
    const before = digits(original.slice(0, position)).length;
    input.value = format(original);
    if (!keepCursor || document.activeElement !== input) return;
    let cursor = 0, seen = 0;
    if (position === original.length) cursor = input.value.length;
    else if (before === 0) cursor = Math.min(position, input.value.search(/\d/) < 0 ? input.value.length : input.value.search(/\d/));
    else {
      while (cursor < input.value.length && seen < before) {
        if (/\d/.test(input.value[cursor])) seen++;
        cursor++;
      }
    }
    input.setSelectionRange(cursor, cursor);
  }
  scope.querySelectorAll('[data-gadsan-mask]').forEach(input => apply(input));
  scope.addEventListener('input', event => {
    if (!event.isComposing && event.target.matches('[data-gadsan-mask]')) apply(event.target, true);
  });
  scope.addEventListener('compositionend', event => {
    if (event.target.matches('[data-gadsan-mask]')) apply(event.target, true);
  });
  scope.addEventListener('keydown', event => {
    const input = event.target;
    if (!input.matches('[data-gadsan-mask]') || event.ctrlKey || event.metaKey || event.altKey) return;
    const start = input.selectionStart, end = input.selectionEnd;
    if (start !== end || !['Backspace', 'Delete'].includes(event.key)) return;
    const backward = event.key === 'Backspace';
    let index = backward ? start - 1 : start;
    if (index < 0 || index >= input.value.length || /\d/.test(input.value[index])) return;
    while (index >= 0 && index < input.value.length && /\D/.test(input.value[index])) index += backward ? -1 : 1;
    if (index < 0 || index >= input.value.length) return;
    event.preventDefault();
    input.value = input.value.slice(0, index) + input.value.slice(index + 1);
    const cursor = backward ? index : start;
    input.setSelectionRange(cursor, cursor);
    apply(input, true);
  });
  scope.addEventListener('focusout', event => {
    if (event.target.matches('[data-gadsan-mask]')) apply(event.target);
    if (event.target.matches('[data-gadsan-email]')) event.target.value = event.target.value.trim().toLowerCase();
  });
})();
