/**
 * Pure TypeScript QR Code Matrix Generator
 * Encodes text into a standard 2D matrix (Versions 1-8, Byte mode, Error Correction Level L).
 * Self-contained without external npm dependencies.
 */

// Galois Field GF(256) math for Reed-Solomon Error Correction
const EXP_TABLE = new Uint8Array(512);
const LOG_TABLE = new Uint8Array(256);

(function initGaloisField() {
  let val = 1;
  for (let i = 0; i < 255; i++) {
    EXP_TABLE[i] = val;
    EXP_TABLE[i + 255] = val;
    LOG_TABLE[val] = i;
    val = (val << 1) ^ (val & 128 ? 0x11d : 0);
  }
  LOG_TABLE[0] = 0;
})();

function gfMultiply(a: number, b: number): number {
  if (a === 0 || b === 0) return 0;
  return EXP_TABLE[LOG_TABLE[a] + LOG_TABLE[b]];
}

function rsGeneratorPoly(degree: number): Uint8Array {
  let poly = new Uint8Array([1]);
  for (let i = 0; i < degree; i++) {
    const next = new Uint8Array(poly.length + 1);
    const root = EXP_TABLE[i];
    for (let j = 0; j < poly.length; j++) {
      next[j] ^= gfMultiply(poly[j], root);
      next[j + 1] ^= poly[j];
    }
    poly = next;
  }
  return poly;
}

function rsCalculateEcc(data: Uint8Array, eccCount: number): Uint8Array {
  const gen = rsGeneratorPoly(eccCount);
  const remainder = new Uint8Array(eccCount);

  for (let i = 0; i < data.length; i++) {
    const factor = data[i] ^ remainder[0];
    for (let j = 0; j < eccCount - 1; j++) {
      remainder[j] = remainder[j + 1] ^ gfMultiply(gen[eccCount - 1 - j], factor);
    }
    remainder[eccCount - 1] = gfMultiply(gen[0], factor);
  }

  return remainder;
}

/**
 * QR Version Capacity table for Byte mode (Level L)
 */
interface QRVersionSpec {
  version: number;
  size: number;
  dataBytes: number;
  eccBytes: number;
  alignmentPatterns: number[];
}

const QR_SPECS: QRVersionSpec[] = [
  { version: 1, size: 21, dataBytes: 19, eccBytes: 7, alignmentPatterns: [] },
  { version: 2, size: 25, dataBytes: 34, eccBytes: 10, alignmentPatterns: [6, 18] },
  { version: 3, size: 29, dataBytes: 55, eccBytes: 15, alignmentPatterns: [6, 22] },
  { version: 4, size: 33, dataBytes: 80, eccBytes: 20, alignmentPatterns: [6, 26] },
  { version: 5, size: 37, dataBytes: 108, eccBytes: 26, alignmentPatterns: [6, 30] },
  { version: 6, size: 41, dataBytes: 136, eccBytes: 36, alignmentPatterns: [6, 34] },
  { version: 7, size: 45, dataBytes: 156, eccBytes: 40, alignmentPatterns: [6, 22, 38] },
  { version: 8, size: 49, dataBytes: 194, eccBytes: 48, alignmentPatterns: [6, 24, 42] },
];

export function generateQrMatrix(text: string): boolean[][] {
  const utf8Bytes = new TextEncoder().encode(text);
  
  // Pick smallest fitting version
  const spec = QR_SPECS.find((s) => s.dataBytes >= utf8Bytes.length + 3) || QR_SPECS[QR_SPECS.length - 1];
  const { size, dataBytes, eccBytes, alignmentPatterns } = spec;

  // 1. Bit Buffer: Byte mode indicator (0100) + 8-bit length + data + 0000 terminator
  const bits: number[] = [0, 1, 0, 0];
  const charCount = Math.min(utf8Bytes.length, dataBytes);
  for (let i = 7; i >= 0; i--) bits.push((charCount >> i) & 1);

  for (let i = 0; i < charCount; i++) {
    const b = utf8Bytes[i];
    for (let j = 7; j >= 0; j--) bits.push((b >> j) & 1);
  }

  // Terminator
  while (bits.length % 8 !== 0) bits.push(0);
  while (bits.length < dataBytes * 8) {
    // Alternate pad bytes 0xEC (11101100) and 0x11 (00010001)
    const pad = (bits.length / 8) % 2 === 0 ? 0xec : 0x11;
    for (let j = 7; j >= 0; j--) bits.push((pad >> j) & 1);
  }

  // Convert bits to data codewords
  const dataCodewords = new Uint8Array(dataBytes);
  for (let i = 0; i < dataBytes; i++) {
    let byteVal = 0;
    for (let j = 0; j < 8; j++) byteVal = (byteVal << 1) | bits[i * 8 + j];
    dataCodewords[i] = byteVal;
  }

  // 2. Compute Reed-Solomon Error Correction Codewords
  const eccCodewords = rsCalculateEcc(dataCodewords, eccBytes);

  // Combine data + ecc
  const allCodewords = new Uint8Array(dataBytes + eccBytes);
  allCodewords.set(dataCodewords, 0);
  allCodewords.set(eccCodewords, dataBytes);

  // 3. Initialize Matrix
  const matrix: (boolean | null)[][] = Array.from({ length: size }, () =>
    Array(size).fill(null)
  );

  // Helper to place 7x7 Finder Pattern with 1px border
  function placeFinderPattern(row: number, col: number) {
    for (let r = -1; r <= 7; r++) {
      for (let c = -1; c <= 7; c++) {
        const mr = row + r;
        const mc = col + c;
        if (mr >= 0 && mr < size && mc >= 0 && mc < size) {
          if (
            (r >= 0 && r <= 6 && (c === 0 || c === 6)) ||
            (c >= 0 && c <= 6 && (r === 0 || r === 6)) ||
            (r >= 2 && r <= 4 && c >= 2 && c <= 4)
          ) {
            matrix[mr][mc] = true;
          } else {
            matrix[mr][mc] = false;
          }
        }
      }
    }
  }

  // Place 3 Finder Patterns
  placeFinderPattern(0, 0);
  placeFinderPattern(0, size - 7);
  placeFinderPattern(size - 7, 0);

  // Timing patterns
  for (let i = 8; i < size - 8; i++) {
    if (matrix[6][i] === null) matrix[6][i] = i % 2 === 0;
    if (matrix[i][6] === null) matrix[i][6] = i % 2 === 0;
  }

  // Dark module
  matrix[size - 8][8] = true;

  // Alignment patterns
  if (alignmentPatterns.length >= 2) {
    for (const r of alignmentPatterns) {
      for (const c of alignmentPatterns) {
        // Skip if overlaps with finders
        if (
          (r < 9 && c < 9) ||
          (r < 9 && c > size - 9) ||
          (r > size - 9 && c < 9)
        ) {
          continue;
        }
        for (let dr = -2; dr <= 2; dr++) {
          for (let dc = -2; dc <= 2; dc++) {
            const isBorder = Math.abs(dr) === 2 || Math.abs(dc) === 2;
            const isCenter = dr === 0 && dc === 0;
            matrix[r + dr][c + dc] = isBorder || isCenter;
          }
        }
      }
    }
  }

  // Format Information Area reserve
  for (let i = 0; i < 9; i++) {
    if (matrix[8][i] === null) matrix[8][i] = false;
    if (matrix[i][8] === null) matrix[i][8] = false;
    if (matrix[8][size - 1 - i] === null) matrix[8][size - 1 - i] = false;
    if (matrix[size - 1 - i][8] === null) matrix[size - 1 - i][8] = false;
  }

  // Standard Format bits for Error Correction Level L and Mask 0
  // (Format string for L-0 is 111011111000100)
  const formatBits = [1, 1, 1, 0, 1, 1, 1, 1, 1, 0, 0, 0, 1, 0, 0];
  const formatPos = [
    [8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5], [8, 7], [8, 8],
    [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8]
  ];
  for (let i = 0; i < 15; i++) {
    const [r, c] = formatPos[i];
    matrix[r][c] = formatBits[i] === 1;
  }

  // 4. Fill Data Bits with Mask 0 ((r + c) % 2 === 0)
  const fullBits: number[] = [];
  for (const byte of allCodewords) {
    for (let j = 7; j >= 0; j--) {
      fullBits.push((byte >> j) & 1);
    }
  }

  let bitIdx = 0;
  let upward = true;

  for (let right = size - 1; right > 0; right -= 2) {
    if (right === 6) right--; // Skip vertical timing column

    const rows = upward
      ? Array.from({ length: size }, (_, i) => size - 1 - i)
      : Array.from({ length: size }, (_, i) => i);

    for (const r of rows) {
      for (const c of [right, right - 1]) {
        if (matrix[r][c] === null) {
          const bit = bitIdx < fullBits.length ? fullBits[bitIdx++] : 0;
          // Apply Mask 0: flip if (r + c) % 2 === 0
          const mask = (r + c) % 2 === 0;
          matrix[r][c] = (bit === 1) !== mask;
        }
      }
    }
    upward = !upward;
  }

  return matrix.map((row) => row.map((cell) => cell ?? false));
}
