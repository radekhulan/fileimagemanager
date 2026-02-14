<template>
  <!-- Hidden SVG sprite — rendered once, referenced via <use href="#..."> -->
  <svg xmlns="http://www.w3.org/2000/svg" style="display:none">
    <!-- ═══ 48×48 icons (grid view) ═══ -->

    <!-- PDF -->
    <symbol id="rfm-i48-pdf" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5">
      <path d="M12 6h16l10 10v26a2 2 0 01-2 2H12a2 2 0 01-2-2V8a2 2 0 012-2z" fill="currentColor" opacity="0.15" />
      <path d="M12 6h16l10 10v26a2 2 0 01-2 2H12a2 2 0 01-2-2V8a2 2 0 012-2z" />
      <path d="M28 6v10h10" />
      <rect x="13" y="26" width="22" height="13" rx="2" fill="currentColor" opacity="0.15" />
      <path d="M18 35v-6h3a2.5 2.5 0 010 5h-3" stroke-width="1.5" />
      <path d="M26 35v-6h3a2.5 2.5 0 011.5 4.5L27 35h3" stroke-width="1.5" />
    </symbol>

    <!-- Word -->
    <symbol id="rfm-i48-word" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5">
      <path d="M12 6h16l10 10v26a2 2 0 01-2 2H12a2 2 0 01-2-2V8a2 2 0 012-2z" fill="currentColor" opacity="0.15" />
      <path d="M12 6h16l10 10v26a2 2 0 01-2 2H12a2 2 0 01-2-2V8a2 2 0 012-2z" />
      <path d="M28 6v10h10" />
      <line x1="16" y1="24" x2="32" y2="24" />
      <line x1="16" y1="30" x2="28" y2="30" />
      <line x1="16" y1="36" x2="24" y2="36" />
    </symbol>

    <!-- Excel -->
    <symbol id="rfm-i48-excel" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5">
      <path d="M12 6h16l10 10v26a2 2 0 01-2 2H12a2 2 0 01-2-2V8a2 2 0 012-2z" fill="currentColor" opacity="0.15" />
      <path d="M12 6h16l10 10v26a2 2 0 01-2 2H12a2 2 0 01-2-2V8a2 2 0 012-2z" />
      <path d="M28 6v10h10" />
      <rect x="14" y="24" width="20" height="14" rx="1" />
      <line x1="14" y1="31" x2="34" y2="31" />
      <line x1="24" y1="24" x2="24" y2="38" />
    </symbol>

    <!-- Video -->
    <symbol id="rfm-i48-video" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5">
      <rect x="4" y="12" width="40" height="28" rx="3" fill="currentColor" opacity="0.15" />
      <rect x="4" y="12" width="40" height="28" rx="3" />
      <path d="M4 19h40" />
      <path d="M12 12l-5 7M22 12l-5 7M32 12l-5 7M42 12l-5 7" stroke-width="1.3" />
      <path d="M20 26v10l9-5-9-5z" fill="currentColor" opacity="0.4" />
    </symbol>

    <!-- Audio -->
    <symbol id="rfm-i48-audio" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5">
      <rect x="6" y="6" width="36" height="36" rx="3" fill="currentColor" opacity="0.15" />
      <polygon points="22 16 14 21 8 21 8 29 14 29 22 34 22 16" fill="currentColor" opacity="0.25" />
      <polygon points="22 16 14 21 8 21 8 29 14 29 22 34 22 16" />
      <path d="M28 20a6 6 0 010 10" stroke-width="2" />
      <path d="M32 16a12 12 0 010 18" stroke-width="2" />
    </symbol>

    <!-- Archive -->
    <symbol id="rfm-i48-archive" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5">
      <path d="M42 32V16a4 4 0 00-2-3.46l-14-8a4 4 0 00-4 0l-14 8A4 4 0 006 16v16a4 4 0 002 3.46l14 8a4 4 0 004 0l14-8A4 4 0 0042 32z" fill="currentColor" opacity="0.15" />
      <path d="M42 32V16a4 4 0 00-2-3.46l-14-8a4 4 0 00-4 0l-14 8A4 4 0 006 16v16a4 4 0 002 3.46l14 8a4 4 0 004 0l14-8A4 4 0 0042 32z" />
      <polyline points="6.5 13.9 24 24 41.5 13.9" />
      <line x1="24" y1="44.2" x2="24" y2="24" />
    </symbol>

    <!-- Image -->
    <symbol id="rfm-i48-image" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5">
      <rect x="6" y="6" width="36" height="36" rx="3" fill="currentColor" opacity="0.15" />
      <rect x="6" y="6" width="36" height="36" rx="3" />
      <circle cx="16" cy="16" r="4" fill="currentColor" opacity="0.4" />
      <path d="M6 34l10-10 8 8 6-6 12 12H8a2 2 0 01-2-2v-2z" fill="currentColor" opacity="0.3" />
    </symbol>

    <!-- Generic -->
    <symbol id="rfm-i48-generic" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5">
      <path d="M12 6h16l10 10v26a2 2 0 01-2 2H12a2 2 0 01-2-2V8a2 2 0 012-2z" fill="currentColor" opacity="0.15" />
      <path d="M12 6h16l10 10v26a2 2 0 01-2 2H12a2 2 0 01-2-2V8a2 2 0 012-2z" />
      <path d="M28 6v10h10" />
      <line x1="16" y1="24" x2="32" y2="24" opacity="0.5" />
      <line x1="16" y1="30" x2="32" y2="30" opacity="0.5" />
      <line x1="16" y1="36" x2="26" y2="36" opacity="0.5" />
    </symbol>

    <!-- ═══ 24×24 icons (list/columns view) ═══ -->

    <!-- PDF -->
    <symbol id="rfm-i24-pdf" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
      <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
      <polyline points="14 2 14 8 20 8" />
      <rect x="6" y="13" width="12" height="7" rx="1" fill="currentColor" opacity="0.15" />
      <path d="M9 17.5v-3h1.5a1.25 1.25 0 010 2.5H9" stroke-width="1.2" />
    </symbol>

    <!-- Word -->
    <symbol id="rfm-i24-word" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
      <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
      <polyline points="14 2 14 8 20 8" />
      <line x1="8" y1="13" x2="16" y2="13" />
      <line x1="8" y1="16" x2="14" y2="16" />
      <line x1="8" y1="19" x2="12" y2="19" />
    </symbol>

    <!-- Excel -->
    <symbol id="rfm-i24-excel" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
      <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
      <polyline points="14 2 14 8 20 8" />
      <rect x="7" y="12" width="10" height="8" rx="0.5" />
      <line x1="7" y1="16" x2="17" y2="16" />
      <line x1="12" y1="12" x2="12" y2="20" />
    </symbol>

    <!-- Video -->
    <symbol id="rfm-i24-video" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
      <rect x="2" y="6" width="20" height="15" rx="2" />
      <path d="M2 10h20" />
      <path d="M6 6l-2.5 4M11 6l-2.5 4M16 6l-2.5 4M21 6l-2.5 4" stroke-width="1.2" />
      <path d="M10 14v5l4.5-2.5L10 14z" fill="currentColor" opacity="0.3" />
    </symbol>

    <!-- Audio -->
    <symbol id="rfm-i24-audio" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
      <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5" fill="currentColor" opacity="0.15" />
      <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5" />
      <path d="M15.54 8.46a5 5 0 010 7.07" />
      <path d="M19.07 4.93a10 10 0 010 14.14" />
    </symbol>

    <!-- Archive -->
    <symbol id="rfm-i24-archive" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
      <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z" />
      <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
      <line x1="12" y1="22.08" x2="12" y2="12" />
    </symbol>

    <!-- Image -->
    <symbol id="rfm-i24-image" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
      <rect x="3" y="3" width="18" height="18" rx="2" />
      <circle cx="8.5" cy="8.5" r="1.5" />
      <path d="M21 15l-5-5L5 21" />
    </symbol>

    <!-- Generic -->
    <symbol id="rfm-i24-generic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
      <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
      <polyline points="14 2 14 8 20 8" />
      <line x1="8" y1="13" x2="16" y2="13" />
      <line x1="8" y1="17" x2="16" y2="17" />
    </symbol>
  </svg>
</template>
