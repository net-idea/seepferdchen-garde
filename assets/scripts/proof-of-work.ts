// Upper bound so a broken challenge can never spin forever (the expected effort is far lower).
const MAX_ATTEMPTS = 2 ** 22;

function hasLeadingZeroBits(hash: Uint8Array, bits: number): boolean {
  const fullBytes = Math.floor(bits / 8);

  for (let i = 0; i < fullBytes; i++) {
    if (hash[i] !== 0) {
      return false;
    }
  }

  const remainingBits = bits % 8;

  return remainingBits === 0 || hash[fullBytes] >> (8 - remainingBits) === 0;
}

/**
 * Solves the spam challenge issued by the server ("<issuedAt>.<salt>.<bits>.<signature>"):
 * finds a counter so that sha256("<challenge>.<counter>") starts with <bits> zero bits.
 *
 * Resolves with an empty string when the puzzle cannot be solved in this browser.
 */
export async function solveChallenge(challenge: string): Promise<string> {
  const bits = Number.parseInt(challenge.split('.')[2] ?? '', 10);
  const subtle = globalThis.crypto?.subtle;

  if (!subtle || Number.isNaN(bits) || bits < 0 || bits > 256) {
    return '';
  }

  const encoder = new TextEncoder();

  try {
    for (let counter = 0; counter < MAX_ATTEMPTS; counter++) {
      const data = encoder.encode(`${challenge}.${counter}`);
      const hash = new Uint8Array(await subtle.digest('SHA-256', data));

      if (hasLeadingZeroBits(hash, bits)) {
        return String(counter);
      }
    }
  } catch {
    return '';
  }

  return '';
}
