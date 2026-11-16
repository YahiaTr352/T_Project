/**
 * Intelligent Arabic Name Search & Relevance Scoring Engine
 * محرك البحث والترتيب الذكي للأسماء العربية وشجرة العائلة
 *
 * Implements "الأقرب ثم الأقرب ثم الأبعد":
 * 1. Prefix matches on full_name & first_name get highest priority (Gen 0 / Self).
 * 2. Multi-token sequential lineage matching (e.g., "ممدوح فهد" -> "ممدوح بن فهد").
 * 3. Exponential generation distance weighting (Self >> Father >> Grandfather).
 * 4. Comprehensive Arabic normalization (tashkeel, hamzat, taa marbouta, alef maqsoora).
 */

export interface SearchablePerson {
  id: number;
  first_name?: string | null;
  last_name?: string | null;
  full_name?: string | null;
  name?: string | null;
  gender?: string | null;
  photo_url?: string | null;
  avatar?: string | null;
  [key: string]: any;
}

const LINEAGE_STOP_WORDS = new Set(['بن', 'ابن', 'بنت', 'ابنه', 'ابنة']);

/**
 * Normalizes Arabic string for robust matching.
 */
export function normalizeArabic(text: string | null | undefined): string {
  if (!text) return '';
  return text
    // Remove diacritics / tashkeel & tatweel
    .replace(/[\u064B-\u065F\u0670\u0640]/g, '')
    // Normalize Alefs
    .replace(/[أإآٱ]/g, 'ا')
    // Normalize Taa Marbouta to Haa
    .replace(/ة/g, 'ه')
    // Normalize Alef Maqsoora to Yaa
    .replace(/ى/g, 'ي')
    // Normalize Hamzated Waw and Yaa
    .replace(/ؤ/g, 'و')
    .replace(/ئ/g, 'ي')
    // Normalize whitespace
    .replace(/\s+/g, ' ')
    .trim()
    .toLowerCase();
}

/**
 * Strips lineage connectives (بن / بنت) to simplify comparisons like "ممدوح فهد" against "ممدوح بن فهد".
 */
export function stripLineageWords(text: string): string {
  return text
    .replace(/\b(بن|ابن|بنت|ابنه|ابنة)\b/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

/**
 * Extracts individual name tokens from a search query, excluding connector words.
 */
export function extractQueryTokens(query: string): string[] {
  const norm = normalizeArabic(query);
  if (!norm) return [];

  return norm
    .split(/\s+/)
    .filter((token) => token.length > 0 && !LINEAGE_STOP_WORDS.has(token));
}

/**
 * Extracts the hierarchical generations from a full name.
 * e.g., "ممدوح بن فهد بن ناصر السريع" -> ["ممدوح", "فهد", "ناصر", "السريع"]
 */
export function extractGenerations(fullName: string, firstName?: string | null): string[] {
  const norm = normalizeArabic(fullName);
  if (!norm) {
    const fnNorm = normalizeArabic(firstName);
    return fnNorm ? [fnNorm] : [];
  }

  const cleaned = stripLineageWords(norm);
  return cleaned.split(/\s+/).filter((g) => g.length > 0);
}

/**
 * Computes the relevance score of a person given a query and its tokens.
 * Higher score = more relevant ("الأقرب").
 * Returns 0 if the person does not match the search criteria.
 */
export function computePersonRelevanceScore(
  person: SearchablePerson,
  rawQuery: string,
  tokens: string[]
): number {
  if (tokens.length === 0) return 0;

  const rawFull = person.full_name || person.name || '';
  const rawFirst = person.first_name || '';

  const normFull = normalizeArabic(rawFull);
  const normFirst = normalizeArabic(rawFirst);
  const cleanQuery = normalizeArabic(rawQuery);

  const cleanFullNoBin = stripLineageWords(normFull);
  const cleanQueryNoBin = stripLineageWords(cleanQuery);

  const generations = extractGenerations(rawFull, rawFirst);
  const gen0 = generations[0] || normFirst;

  let score = 0;
  let matchesAllTokens = true;
  let matchedTokensCount = 0;

  // Check if each token exists anywhere in the person's lineage
  for (let i = 0; i < tokens.length; i++) {
    const token = tokens[i];
    const isLastToken = i === tokens.length - 1;

    let tokenFoundInPerson = false;
    for (let g = 0; g < generations.length; g++) {
      const genName = generations[g];
      if (genName === token || (isLastToken && genName.startsWith(token)) || genName.includes(token)) {
        tokenFoundInPerson = true;
        break;
      }
    }

    if (!tokenFoundInPerson && !normFull.includes(token)) {
      matchesAllTokens = false;
    } else {
      matchedTokensCount++;
    }
  }

  if (tokens.length >= 2 && !matchesAllTokens) {
    return 0;
  }

  if (tokens.length === 1 && matchedTokensCount === 0) {
    return 0;
  }

  // TIER 1: Full Name Prefix Match (Highest Priority)
  if (normFull.startsWith(cleanQuery)) {
    score += 10000;
  } else if (cleanFullNoBin.startsWith(cleanQueryNoBin)) {
    score += 8500;
  }

  // TIER 2: First Name / Self (Generation 0) Exact/Prefix Match
  const firstToken = tokens[0];
  if (gen0 === firstToken || normFirst === firstToken) {
    score += 5000;
  } else if (gen0.startsWith(firstToken) || normFirst.startsWith(firstToken)) {
    score += 3000;
  } else if (gen0.includes(firstToken) || normFirst.includes(firstToken)) {
    score += 1500;
  }

  // TIER 3: Sequential Lineage Alignment
  let consecutiveMatches = 0;
  for (let i = 0; i < tokens.length && i < generations.length; i++) {
    const token = tokens[i];
    const gen = generations[i];
    const isLast = i === tokens.length - 1;

    if (gen === token || (isLast && gen.startsWith(token))) {
      consecutiveMatches++;
      const genMultiplier = Math.max(1, 4 - i);
      score += 1000 * genMultiplier;
    } else {
      break;
    }
  }

  if (consecutiveMatches >= 2) {
    score += 2500;
  }

  // TIER 4: Generational Distance & Position Weight
  for (let g = 0; g < generations.length; g++) {
    const gen = generations[g];
    if (gen === firstToken || gen.startsWith(firstToken)) {
      if (g === 0) {
        score += 1000; // Self
      } else if (g === 1) {
        score += 250;  // Father
      } else if (g === 2) {
        score += 60;   // Grandfather
      } else {
        score += 15;   // Great-grandfather+
      }
      break;
    }
  }

  // TIER 5: Position Index in Full Name
  const matchIndex = normFull.indexOf(firstToken);
  if (matchIndex >= 0) {
    score += Math.max(0, 400 - matchIndex * 10);
  }

  // TIER 6: Completeness Bonus
  if (matchesAllTokens && tokens.length > 0) {
    score += 500 * tokens.length;
  }

  return score;
}

/**
 * Searches and ranks a list of persons according to Arabic lineage relevance.
 * Results are sorted strictly descending by score: closest matches first.
 */
export function searchAndRankPersons<T extends SearchablePerson>(
  persons: T[],
  query: string,
  limit: number = 30
): T[] {
  if (!Array.isArray(persons) || persons.length === 0) {
    return [];
  }

  const raw = (query || '').trim();
  if (!raw) {
    return [];
  }

  const tokens = extractQueryTokens(raw);
  if (tokens.length === 0) {
    return [];
  }

  const scoredList: { person: T; score: number }[] = [];

  for (let i = 0; i < persons.length; i++) {
    const person = persons[i];
    if (!person) continue;

    const score = computePersonRelevanceScore(person, raw, tokens);
    if (score > 0) {
      scoredList.push({ person, score });
    }
  }

  scoredList.sort((a, b) => {
    if (b.score !== a.score) {
      return b.score - a.score;
    }
    const lenA = (a.person.full_name || a.person.name || '').length;
    const lenB = (b.person.full_name || b.person.name || '').length;
    if (lenA !== lenB) {
      return lenA - lenB;
    }
    return a.person.id - b.person.id;
  });

  return scoredList.slice(0, limit).map((item) => item.person);
}
