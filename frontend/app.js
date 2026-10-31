/**
 * ScenePulse by Flownie — Frontend Application
 * Real-Time Video Frame (<canvas>) + 10s Audio -> Schema-Aware LLM -> Qloo /search & /v2/insights
 * 100% English UI & Comments · Light Editorial Theme · Zero Fake Metrics
 */

const state = {
  videos: [],
  activeVideoIndex: 0,
  activeImpulse: null,
  selectedOfferIndex: 0,
  activeView: 'stage', // 'stage' | 'detail' | 'how'
  customVideoObjectUrl: null,
  customVideoName: null,
  lastCapturedFrameDataUri: null,
  waveformTimer: null,
  llmStatus: null,
  qlooStatus: null,
};

const els = {
  // Navigation & Views
  brandHomeTrigger: document.getElementById('brandHomeTrigger'),
  btnViewStage: document.getElementById('btnViewStage'),
  btnViewDetail: document.getElementById('btnViewDetail'),
  btnViewHow: document.getElementById('btnViewHow'),
  btnUploadCustomVideo: document.getElementById('btnUploadCustomVideo'),
  customVideoInput: document.getElementById('customVideoInput'),
  qlooStatusDot: document.getElementById('qlooStatusDot'),
  qlooStatusText: document.getElementById('qlooStatusText'),
  llmStatusText: document.getElementById('llmStatusText'),
  bgEditorialWatermark: document.getElementById('bgEditorialWatermark'),
  screenStage: document.getElementById('screenStage'),
  screenDetail: document.getElementById('screenDetail'),
  screenHowItWorks: document.getElementById('screenHowItWorks'),

  // Screen 1: Left Qloo /v2/insights Stream
  leftStreamBucketBadge: document.getElementById('leftStreamBucketBadge'),
  leftRadarBpm: document.getElementById('leftRadarBpm'),
  leftWaveformBars: document.getElementById('leftWaveformBars'),
  leftAudioSummaryText: document.getElementById('leftAudioSummaryText'),
  leftDynamicDomainsLabel: document.getElementById('leftDynamicDomainsLabel'),
  leftStreamSourceTag: document.getElementById('leftStreamSourceTag'),
  leftLiveAdFeed: document.getElementById('leftLiveAdFeed'),

  // Screen 1: Center Aligned Video Cards
  stageAlignedCardsRow: document.getElementById('stageAlignedCardsRow'),

  // Screen 1: Right Algorithm Summary Card
  stageRightCounter: document.getElementById('stageRightCounter'),
  stageRightKicker: document.getElementById('stageRightKicker'),
  stageRightTitle: document.getElementById('stageRightTitle'),
  stageRightCreator: document.getElementById('stageRightCreator'),
  stageStatSeeds: document.getElementById('stageStatSeeds'),
  stageStatAffinity: document.getElementById('stageStatAffinity'),
  stageStatDuration: document.getElementById('stageStatDuration'),
  stageRightImpulsePreview: document.getElementById('stageRightImpulsePreview'),
  btnStageOpenDetail: document.getElementById('btnStageOpenDetail'),
  btnStageOpenHow: document.getElementById('btnStageOpenHow'),

  // Screen 2: Detail Studio Left Column
  detailVideoPlayer: document.getElementById('detailVideoPlayer'),
  frameCaptureCanvas: document.getElementById('frameCaptureCanvas'),
  videoHudOverlayLayer: document.getElementById('videoHudOverlayLayer'),
  btnPlayPauseVideo: document.getElementById('btnPlayPauseVideo'),
  btnCaptureSceneNow: document.getElementById('btnCaptureSceneNow'),
  btnToggleMute: document.getElementById('btnToggleMute'),
  detailPulseIndicator: document.getElementById('detailPulseIndicator'),
  detailCaptureStatusText: document.getElementById('detailCaptureStatusText'),
  fourAdsSectionTitle: document.getElementById('fourAdsSectionTitle'),
  fourAdsSourceBadge: document.getElementById('fourAdsSourceBadge'),
  fourAlignedAdsGrid: document.getElementById('fourAlignedAdsGrid'),

  // Screen 2: Detail Studio Right Column
  detailCategoryTags: document.getElementById('detailCategoryTags'),
  detailSerifHeading: document.getElementById('detailSerifHeading'),
  detailAuthorLine: document.getElementById('detailAuthorLine'),
  metricAffinity: document.getElementById('metricAffinity'),
  metricSeedCount: document.getElementById('metricSeedCount'),
  metricBucketTime: document.getElementById('metricBucketTime'),
  metricLatency: document.getElementById('metricLatency'),
  detailSignalTags: document.getElementById('detailSignalTags'),
  btnBackToStage: document.getElementById('btnBackToStage'),
  btnCopyOfferText: document.getElementById('btnCopyOfferText'),
  btnForceLiveRefresh: document.getElementById('btnForceLiveRefresh'),

  // Algorithm Proof Box
  selectedOfferUrnBadge: document.getElementById('selectedOfferUrnBadge'),
  selectedOfferTitle: document.getElementById('selectedOfferTitle'),
  selectedOfferHook: document.getElementById('selectedOfferHook'),
  selectedOfferCoupon: document.getElementById('selectedOfferCoupon'),
  selectedOfferReasoning: document.getElementById('selectedOfferReasoning'),
  selectedOfferQlooDescription: document.getElementById('selectedOfferQlooDescription'),
  selectedOfferQlooMeta: document.getElementById('selectedOfferQlooMeta'),
  selectedOfferKeywords: document.getElementById('selectedOfferKeywords'),
  capturedFramePreviewImg: document.getElementById('capturedFramePreviewImg'),
  detailVisualSummary: document.getElementById('detailVisualSummary'),
  detailAudioSummary: document.getElementById('detailAudioSummary'),
  resolvedQlooSeedsList: document.getElementById('resolvedQlooSeedsList'),
  plannedQlooUrnsList: document.getElementById('plannedQlooUrnsList'),

  // Live Qloo Explorer
  qlooExploreQueryInput: document.getElementById('qlooExploreQueryInput'),
  qlooExploreTypeSelect: document.getElementById('qlooExploreTypeSelect'),
  btnRunQlooExplore: document.getElementById('btnRunQlooExplore'),
  qlooExploreResultBox: document.getElementById('qlooExploreResultBox'),

  // Screen 3: How It Works (Remotion)
  remotionWalkthroughVideo: document.getElementById('remotionWalkthroughVideo'),
  btnHowToStudio: document.getElementById('btnHowToStudio'),
  btnHowToStage: document.getElementById('btnHowToStage'),

  // Toast
  toastBanner: document.getElementById('toastBanner'),
};

function showToast(message) {
  if (!els.toastBanner) return;
  els.toastBanner.textContent = message;
  els.toastBanner.classList.remove('hidden');
  clearTimeout(showToast._timer);
  showToast._timer = setTimeout(() => {
    els.toastBanner.classList.add('hidden');
  }, 3500);
}

function escapeHtml(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function getActiveVideo() {
  return state.videos[state.activeVideoIndex] || null;
}

function pickImpulseForTimestamp(video, seconds = 5) {
  if (!video || !Array.isArray(video.impulses) || video.impulses.length === 0) {
    return null;
  }
  const targetBucket = Math.floor(Number(seconds || 0) / 5) * 5;
  let best = video.impulses[0];
  let bestDiff = Math.abs((best.time_bucket ?? 0) - targetBucket);
  for (const imp of video.impulses) {
    const diff = Math.abs((imp.time_bucket ?? 0) - targetBucket);
    if (diff < bestDiff) {
      best = imp;
      bestDiff = diff;
    }
  }
  return best;
}

function computeAverageAffinity(offers) {
  if (!Array.isArray(offers) || offers.length === 0) return 0;
  const sum = offers.reduce((acc, o) => acc + Number(o.qloo_affinity_score || 0), 0);
  return Math.round((sum / offers.length) * 10) / 10;
}

function renderQlooImageOrBadge(offer, className = 'live-ad-thumb') {
  if (offer.image_url) {
    return `<img
      class="${className}"
      src="${escapeHtml(offer.image_url)}"
      alt="${escapeHtml(offer.qloo_entity_name)}"
      loading="lazy"
    />`;
  }
  const shortId = String(offer.qloo_entity_id || '').slice(0, 8);
  return `<div
    class="${className}"
    style="display:flex; flex-direction:column; align-items:center; justify-content:center; background:#F3F1EB; color:var(--accent-primary); border:1px solid var(--border-subtle); font-family:var(--font-mono); font-size:10px; font-weight:700; text-align:center; padding:6px;"
  >
    <span>${escapeHtml(offer.category_badge || 'QLOO')}</span>
    <span style="font-size:8.5px; opacity:0.75;">${escapeHtml(shortId)}</span>
  </div>`;
}

async function initApp() {
  bindEvents();
  startWaveformAnimation();
  await loadCatalog();
}

async function loadCatalog() {
  try {
    const res = await fetch('/api/v1/scenes/catalog');
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();

    state.videos = Array.isArray(data.videos) ? data.videos : [];
    state.qlooStatus = data.qloo_status || null;
    state.llmStatus = data.llm_status || null;

    if (data.qloo_status && data.qloo_status.api_key_configured) {
      els.qlooStatusDot.className = 'pulse-dot green';
      els.qlooStatusText.textContent = `Qloo API · Live (${data.qloo_status.base_url.replace('https://', '')})`;
    } else {
      els.qlooStatusDot.className = 'pulse-dot';
      els.qlooStatusText.textContent = 'Qloo API · Awaiting Key';
    }

    if (els.llmStatusText && data.llm_status) {
      const provider = String(data.llm_status.active_provider || 'llm').toUpperCase();
      els.llmStatusText.textContent = `LLM · ${provider} Schema Planner`;
    }

    if (state.videos.length > 0) {
      selectVideoByIndex(0, 5);
    }
  } catch (err) {
    console.error('Backend catalog fetch error:', err);
    showToast('Could not connect to the ScenePulse backend API.');
  }
}

function selectVideoByIndex(index, preferredSeconds = 5) {
  if (!state.videos.length) return;
  const normalized = ((index % state.videos.length) + state.videos.length) % state.videos.length;
  state.activeVideoIndex = normalized;
  state.customVideoObjectUrl = null;
  state.customVideoName = null;
  state.lastCapturedFrameDataUri = null;

  const video = getActiveVideo();
  state.activeImpulse = pickImpulseForTimestamp(video, preferredSeconds);
  state.selectedOfferIndex = 0;

  renderStageScreen();
  renderDetailScreen(true);
}

/* ==========================================================================
   SCREEN 1 RENDERING: Left Live Qloo Stream + Center 3 Video Cards + Right Algorithm Summary
   ========================================================================== */
function renderStageScreen() {
  const video = getActiveVideo();
  if (!video) return;
  const impulse = state.activeImpulse || pickImpulseForTimestamp(video, 5);
  const offers = Array.isArray(impulse?.ad_offers) ? impulse.ad_offers : [];
  const resolvedSeeds = Array.isArray(impulse?.qloo_resolved_entities) ? impulse.qloo_resolved_entities : [];

  // Watermark update
  if (els.bgEditorialWatermark) {
    els.bgEditorialWatermark.textContent = `SCENE 0${state.activeVideoIndex + 1}`;
  }

  // 1. Render Center Horizontally Aligned 3 Real Video Cards
  els.stageAlignedCardsRow.innerHTML = state.videos
    .map((v, idx) => {
      const isActive = idx === state.activeVideoIndex;
      const vImpulse = pickImpulseForTimestamp(v, 5);
      const topOffer = vImpulse?.ad_offers?.[0];
      const shortTitle = v.subtitle || v.title;
      const seedCount = Array.isArray(vImpulse?.qloo_resolved_entities)
        ? vImpulse.qloo_resolved_entities.length
        : (v.qloo_seed_queries || []).length;

      return `
        <article
          class="floating-video-card ${isActive ? 'active' : ''}"
          data-video-index="${idx}"
          role="button"
          tabindex="0"
          aria-label="${escapeHtml(v.title)}"
        >
          <div class="card-media-wrap">
            <img src="${escapeHtml(v.poster_url)}" alt="${escapeHtml(v.title)}" loading="lazy" />
            <span class="card-red-dot" title="Live Video Frame + Qloo /v2/insights"></span>
            <span class="card-duration-badge">${escapeHtml(v.duration_badge)}</span>
            ${
              topOffer
                ? `<div class="card-in-video-hud">
                    <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                      ${escapeHtml(topOffer.category_badge || 'QLOO')}: ${escapeHtml(topOffer.qloo_entity_name)}
                    </span>
                    <span class="hud-offer-tag">${escapeHtml(topOffer.qloo_affinity_score)}%</span>
                  </div>`
                : ''
            }
          </div>
          <div class="card-bottom-bar">
            <div class="card-title-short" title="${escapeHtml(shortTitle)}">
              ${escapeHtml(shortTitle)}
            </div>
            <div class="card-badges-right">
              <span class="demo-pill">${seedCount} SEEDS</span>
            </div>
          </div>
        </article>
      `;
    })
    .join('');

  els.stageAlignedCardsRow.querySelectorAll('.floating-video-card').forEach((cardEl) => {
    cardEl.addEventListener('click', () => {
      const idx = Number(cardEl.getAttribute('data-video-index'));
      if (idx === state.activeVideoIndex) {
        switchView('detail');
      } else {
        selectVideoByIndex(idx, 5);
      }
    });
  });

  // 2. Render Left Real-Time Qloo /v2/insights Stream Panel
  const timeFormatted = impulse?.timestamp_formatted || '00:05';
  els.leftStreamBucketBadge.textContent = `T=${timeFormatted}`;

  const audioSignals = Array.isArray(impulse?.audio_10s_signals)
    ? impulse.audio_10s_signals
    : Array.isArray(video.default_audio_signals)
    ? video.default_audio_signals
    : [];
  els.leftAudioSummaryText.textContent =
    audioSignals[0] || '10s audio spectrum and frame telemetry ready.';

  const bpmMatch = (audioSignals.join(' ') || '').match(/(\d{2,3}\s*BPM)/i);
  els.leftRadarBpm.textContent = bpmMatch ? `${bpmMatch[1]} · 10s Audio` : '10s Audio Window';

  const domainBadges = offers.map((o) => o.category_badge || o.category_key || 'QLOO');
  if (els.leftDynamicDomainsLabel && domainBadges.length) {
    els.leftDynamicDomainsLabel.textContent = `DOMAINS: ${domainBadges.join(' · ')}`;
  }

  els.leftStreamSourceTag.textContent = impulse?.qloo_live_verified
    ? 'LIVE QLOO /V2/INSIGHTS'
    : '5S BUCKET CACHE';

  els.leftLiveAdFeed.innerHTML = offers
    .map((offer, oIdx) => {
      const isSelected = oIdx === state.selectedOfferIndex;
      return `
        <div class="live-ad-mini-card ${isSelected ? 'active' : ''}" data-offer-index="${oIdx}">
          ${renderQlooImageOrBadge(offer, 'live-ad-thumb')}
          <div class="live-ad-info">
            <div class="live-ad-meta">
              <span class="ad-cat-pill">${escapeHtml(offer.category_badge || offer.category_label)}</span>
              <span class="ad-match-score">${escapeHtml(offer.qloo_affinity_score)}% Affinity</span>
            </div>
            <div class="live-ad-name">${escapeHtml(offer.qloo_entity_name)}</div>
            <div class="live-ad-sub">${escapeHtml(offer.ad_headline)}</div>
          </div>
        </div>
      `;
    })
    .join('');

  els.leftLiveAdFeed.querySelectorAll('.live-ad-mini-card').forEach((itemEl) => {
    itemEl.addEventListener('click', () => {
      const oIdx = Number(itemEl.getAttribute('data-offer-index'));
      state.selectedOfferIndex = oIdx;
      switchView('detail');
      renderDetailOfferSelection();
    });
  });

  // 3. Render Right Algorithm Summary Card
  els.stageRightCounter.innerHTML = `<strong>SCENE 0${state.activeVideoIndex + 1}</strong> / 0${state.videos.length}`;

  const pills = Array.isArray(video.category_pills) ? video.category_pills : [];
  els.stageRightKicker.textContent = pills.slice(0, 3).join(' · ');
  els.stageRightTitle.textContent = video.subtitle || video.title;
  els.stageRightCreator.innerHTML = `<strong>${escapeHtml(video.creator_handle)}</strong> · ${escapeHtml(video.creator_followers || '')}`;

  const avgAff = computeAverageAffinity(offers);
  els.stageStatSeeds.textContent = `${resolvedSeeds.length || (video.qloo_seed_queries || []).length} UUIDs`;
  els.stageStatAffinity.textContent = avgAff ? `${avgAff}%` : '—';
  els.stageStatDuration.textContent = video.duration_badge || '—';

  const seedNames = resolvedSeeds.map((s) => `${s.name}`).join(', ');
  const plannedUrns = offers.map((o) => o.category_urn).join(', ');
  const offerNames = offers.map((o) => `${o.qloo_entity_name} (${o.qloo_affinity_score}%)`).join(' · ');
  els.stageRightImpulsePreview.innerHTML = `
    <div style="margin-bottom:4px;"><strong>Qloo /search Seeds:</strong> ${escapeHtml(seedNames || (video.qloo_seed_queries || []).join(', '))}</div>
    <div style="margin-bottom:4px;"><strong>LLM Selected URNs:</strong> <code>${escapeHtml(plannedUrns)}</code></div>
    <div><strong>Qloo /v2/insights Output:</strong> ${escapeHtml(offerNames)}</div>
  `;
}

/* ==========================================================================
   SCREEN 2 RENDERING: Video Detail & Real-Time Qloo Algorithm Proof Studio
   ========================================================================== */
function renderDetailScreen(resetVideoSource = false) {
  const video = getActiveVideo();
  if (!video) return;
  const isCustomAwaitingCapture = Boolean(state.customVideoObjectUrl && !state.activeImpulse);
  const impulse = isCustomAwaitingCapture
    ? null
    : state.activeImpulse || pickImpulseForTimestamp(video, 5);
  const resolvedSeeds = Array.isArray(impulse?.qloo_resolved_entities) ? impulse.qloo_resolved_entities : [];

  if (resetVideoSource && els.detailVideoPlayer) {
    const targetSrc = state.customVideoObjectUrl || video.video_url;
    if (els.detailVideoPlayer.getAttribute('src') !== targetSrc) {
      els.detailVideoPlayer.src = targetSrc;
      els.detailVideoPlayer.poster = state.customVideoObjectUrl ? '' : video.poster_url || '';
      els.detailVideoPlayer.muted = false;
      updateMuteButtonUI();
    }
  }

  // Right Top Category Pills
  if (state.customVideoObjectUrl) {
    const customDomainPills = Array.isArray(impulse?.ad_offers)
      ? impulse.ad_offers.map((o) => o.category_badge || o.category_key).filter(Boolean)
      : ['CUSTOM VIDEO UPLOAD'];
    els.detailCategoryTags.innerHTML =
      customDomainPills.map((p) => `<span class="cat-chip">${escapeHtml(p)}</span>`).join('') +
      `<span class="holographic-pill">${isCustomAwaitingCapture ? 'READY FOR LIVE CAPTURE' : 'LIVE VISION + QLOO PROOF'}</span>`;
  } else {
    const pills = Array.isArray(video.category_pills) ? video.category_pills : [];
    els.detailCategoryTags.innerHTML =
      pills.map((p) => `<span class="cat-chip">${escapeHtml(p)}</span>`).join('') +
      `<span class="holographic-pill">LIVE ALGORITHM PROOF</span>`;
  }

  // Heading & Technical Pipeline Line
  els.detailSerifHeading.textContent = state.customVideoName
    ? `${state.customVideoName} — Live Frame + 10s Audio Analysis`
    : video.title;

  const modelUsed = impulse?.metrics?.model_used || state.llmStatus?.active_model || 'google/gemma-3-27b-it';
  const qlooBaseUrl = state.qlooStatus?.base_url || 'https://hackathon.api.qloo.com';
  els.detailAuthorLine.innerHTML = `<strong>LLM Planner:</strong> ${escapeHtml(modelUsed)} · <strong>Qloo Endpoint:</strong> ${escapeHtml(qlooBaseUrl)}`;

  // 4-Column Divided Algorithm Telemetry Grid
  const offers = Array.isArray(impulse?.ad_offers) ? impulse.ad_offers : [];
  const selectedOffer = offers[state.selectedOfferIndex] || offers[0];
  els.metricAffinity.textContent = selectedOffer ? `${selectedOffer.qloo_affinity_score}%` : '—';
  els.metricSeedCount.textContent = isCustomAwaitingCapture ? '0 UUIDs' : `${resolvedSeeds.length} UUIDs`;
  els.metricBucketTime.textContent = isCustomAwaitingCapture
    ? 'READY'
    : `T=${impulse?.timestamp_formatted || '00:05'}`;
  els.metricLatency.textContent = impulse?.latency_ms ? `${impulse.latency_ms} ms` : 'Ready';

  if (els.fourAdsSectionTitle) {
    if (offers.length) {
      const badgeList = offers.map((o) => o.category_badge || o.category_key).join(' · ');
      els.fourAdsSectionTitle.textContent = `4 LLM-SELECTED QLOO /V2/INSIGHTS MATCHES (${badgeList})`;
    } else {
      els.fourAdsSectionTitle.textContent =
        'AWAITING LIVE FRAME CAPTURE — CLICK "CAPTURE LIVE FRAME" TO EXTRACT QLOO ENTITIES';
    }
  }

  // Cultural Signal & Resolved Qloo Seed Pills
  if (isCustomAwaitingCapture) {
    els.detailSignalTags.innerHTML = `<span class="holographic-pill">⚡ Custom video ready — Click "Capture Live Frame &amp; Force Live API Call" below to extract real-time Qloo seeds</span>`;
  } else {
    const tags = Array.isArray(impulse?.qloo_taste_tags) ? impulse.qloo_taste_tags : [];
    const seedBadges = resolvedSeeds.map((s) => `SEED: ${s.name} (${String(s.entity_id).slice(0, 8)})`);
    const combinedSignals = [...seedBadges, ...tags];
    els.detailSignalTags.innerHTML = combinedSignals
      .map((tag, i) =>
        i < resolvedSeeds.length
          ? `<span class="holographic-pill">${escapeHtml(tag)}</span>`
          : `<span class="cat-chip" style="background:#FFFFFF;">${escapeHtml(tag)}</span>`
      )
      .join('');
  }

  renderDetailOfferSelection();
}

function renderDetailOfferSelection() {
  const video = getActiveVideo();
  const isCustomAwaitingCapture = Boolean(state.customVideoObjectUrl && !state.activeImpulse);
  const impulse = isCustomAwaitingCapture
    ? null
    : state.activeImpulse || pickImpulseForTimestamp(video, 5);
  const offers = Array.isArray(impulse?.ad_offers) ? impulse.ad_offers : [];
  const resolvedSeeds = Array.isArray(impulse?.qloo_resolved_entities) ? impulse.qloo_resolved_entities : [];

  if (isCustomAwaitingCapture || !offers.length) {
    els.videoHudOverlayLayer.innerHTML = '';
    els.fourAlignedAdsGrid.innerHTML = `
      <div style="grid-column: 1 / -1; background: var(--bg-surface); border: 1px dashed var(--border-strong); border-radius: 14px; padding: 22px; text-align: center; color: var(--text-secondary);">
        <div style="font-family: var(--font-mono); font-size: 11.5px; font-weight: 700; color: var(--accent-primary); margin-bottom: 6px;">
          ⚡ CLEAN CUSTOM VIDEO WORKSPACE — ZERO PRE-SEEDED ENTITIES
        </div>
        <div style="font-size: 13px; color: var(--text-primary); font-weight: 500;">
          Play or scrub your uploaded video to any scene, then click <strong>"⚡ Capture Live Frame &amp; Run Qloo AI"</strong> to extract real visual cues via <code>google/gemma-3-27b-it</code> and resolve live Qloo <code>/search</code> &amp; <code>/v2/insights</code> matches.
        </div>
      </div>
    `;
    els.selectedOfferUrnBadge.textContent = 'AWAITING LIVE CAPTURE';
    els.selectedOfferTitle.textContent = 'Ready to Analyze Uploaded Video Frame';
    els.selectedOfferHook.textContent =
      'Click "⚡ Capture Live Frame & Force Live API Call" to inspect this video';
    els.selectedOfferCoupon.textContent = 'LIVE-READY';
    els.selectedOfferReasoning.textContent =
      'Custom uploads start with a clean slate (no pre-loaded showcase seeds). When you capture a frame, ScenePulse snapshots the exact HTML5 <canvas> bitmap, extracts on-screen brands/topics/context with Gemma-3-27B Vision, resolves Qloo entity UUIDs via /search, and fetches 4 cross-domain /v2/insights recommendations.';
    if (els.selectedOfferQlooDescription) {
      els.selectedOfferQlooDescription.textContent =
        'Waiting for live HTML5 <canvas> frame capture...';
    }
    els.selectedOfferQlooMeta.textContent = 'Qloo Entity UUID: Awaiting live frame capture';
    if (els.selectedOfferKeywords) {
      els.selectedOfferKeywords.textContent = '';
    }
    if (els.capturedFramePreviewImg) {
      els.capturedFramePreviewImg.src = state.lastCapturedFrameDataUri || '';
    }
    els.detailVisualSummary.textContent = 'Waiting for live <canvas> frame snapshot...';
    els.detailAudioSummary.textContent = 'Custom video stream ready for multimodal analysis.';
    if (els.resolvedQlooSeedsList) els.resolvedQlooSeedsList.innerHTML = '';
    if (els.plannedQlooUrnsList) els.plannedQlooUrnsList.innerHTML = '';
    return;
  }

  if (state.selectedOfferIndex >= offers.length) {
    state.selectedOfferIndex = 0;
  }

  // 1. In-Video Floating Hotspot Pins over the Video Viewport
  els.videoHudOverlayLayer.innerHTML = offers
    .map((offer, idx) => {
      const x = offer.hud_position?.x ?? 22 + idx * 20;
      const y = offer.hud_position?.y ?? 30 + (idx % 2) * 35;
      const isSelected = idx === state.selectedOfferIndex;
      return `
        <button
          type="button"
          class="hud-hotspot-pin ${isSelected ? 'selected' : ''}"
          style="left: ${x}%; top: ${y}%;"
          data-offer-index="${idx}"
          title="${escapeHtml(offer.ad_headline)}"
        >
          <span class="pulse-dot ${isSelected ? 'green' : ''}"></span>
          <span>${escapeHtml(offer.category_badge)}: ${escapeHtml(offer.qloo_entity_name)}</span>
          <span class="hud-offer-tag">${escapeHtml(offer.qloo_affinity_score)}%</span>
        </button>
      `;
    })
    .join('');

  els.videoHudOverlayLayer.querySelectorAll('.hud-hotspot-pin').forEach((pinEl) => {
    pinEl.addEventListener('click', (e) => {
      e.stopPropagation();
      state.selectedOfferIndex = Number(pinEl.getAttribute('data-offer-index'));
      renderDetailOfferSelection();
    });
  });

  // 2. Bottom-Left 4 Horizontally Aligned Qloo Insights Cards
  els.fourAlignedAdsGrid.innerHTML = offers
    .slice(0, 4)
    .map((offer, idx) => {
      const isSelected = idx === state.selectedOfferIndex;
      const shortUuid = String(offer.qloo_entity_id || '').slice(0, 8);
      return `
        <article
          class="aligned-ad-card ${isSelected ? 'selected' : ''}"
          data-offer-index="${idx}"
          role="button"
          tabindex="0"
        >
          <div class="aligned-ad-img-wrap">
            ${
              offer.image_url
                ? `<img
                    src="${escapeHtml(offer.image_url)}"
                    alt="${escapeHtml(offer.qloo_entity_name)}"
                    loading="lazy"
                  />`
                : `<div style="width:100%; height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; background:#F3F1EB; color:var(--accent-primary); font-family:var(--font-mono); padding:8px; text-align:center;">
                    <span style="font-size:11px; font-weight:700;">${escapeHtml(offer.category_urn)}</span>
                    <span style="font-size:9.5px; opacity:0.8; margin-top:2px;">UUID: ${escapeHtml(shortUuid)}</span>
                  </div>`
            }
            <span class="aligned-ad-badge-overlay">${escapeHtml(offer.category_badge || offer.category_label)}</span>
            <span class="aligned-ad-affinity-overlay">${escapeHtml(offer.qloo_affinity_score)}%</span>
          </div>
          <div class="aligned-ad-title" title="${escapeHtml(offer.qloo_entity_name)}">
            ${escapeHtml(offer.qloo_entity_name)}
          </div>
          <div class="aligned-ad-sub" title="Qloo Entity UUID: ${escapeHtml(offer.qloo_entity_id)}">
            UUID: ${escapeHtml(shortUuid)}… · ${escapeHtml(offer.offer_badge || '')}
          </div>
        </article>
      `;
    })
    .join('');

  els.fourAlignedAdsGrid.querySelectorAll('.aligned-ad-card').forEach((cardEl) => {
    cardEl.addEventListener('click', () => {
      state.selectedOfferIndex = Number(cardEl.getAttribute('data-offer-index'));
      renderDetailOfferSelection();
    });
  });

  // 3. Update Right Column Algorithm Proof Box
  const selected = offers[state.selectedOfferIndex] || offers[0];
  els.selectedOfferUrnBadge.textContent = `${selected.category_badge || 'QLOO'} · ${selected.category_urn}`;
  els.selectedOfferTitle.textContent = selected.qloo_entity_name;
  els.selectedOfferHook.textContent = `${selected.ad_headline} (${selected.offer_badge || ''})`;
  els.selectedOfferCoupon.textContent = selected.coupon_code || 'QLOO-VERIFIED';
  els.selectedOfferReasoning.textContent = selected.why_this_ad || '';

  if (els.selectedOfferQlooDescription) {
    els.selectedOfferQlooDescription.textContent = selected.qloo_description
      ? `Qloo Entity Description: ${selected.qloo_description}`
      : `Qloo Subtype: ${selected.qloo_subtype || selected.category_urn}`;
  }

  els.selectedOfferQlooMeta.textContent = `Qloo Entity UUID: ${selected.qloo_entity_id} · Qloo Affinity: ${selected.qloo_affinity_score}%`;

  if (els.selectedOfferKeywords) {
    const kw = Array.isArray(selected.qloo_keywords) ? selected.qloo_keywords.slice(0, 4).join(', ') : '';
    els.selectedOfferKeywords.textContent = kw ? `Qloo Keywords: ${kw}` : '';
  }

  els.metricAffinity.textContent = `${selected.qloo_affinity_score}%`;

  // Captured Frame Thumbnail + Visual/Audio Signals + Resolved Seeds + Planned URNs
  if (els.capturedFramePreviewImg) {
    els.capturedFramePreviewImg.src = state.lastCapturedFrameDataUri || video?.poster_url || '';
  }

  const vSignals = Array.isArray(impulse?.visual_signals)
    ? impulse.visual_signals
    : Array.isArray(video?.default_visual_signals)
    ? video.default_visual_signals
    : [];
  const aSignals = Array.isArray(impulse?.audio_10s_signals)
    ? impulse.audio_10s_signals
    : Array.isArray(video?.default_audio_signals)
    ? video.default_audio_signals
    : [];

  els.detailVisualSummary.textContent = vSignals.join(' · ') || '—';
  els.detailAudioSummary.textContent = aSignals.join(' · ') || '—';

  if (els.resolvedQlooSeedsList) {
    els.resolvedQlooSeedsList.innerHTML = resolvedSeeds.length
      ? `<strong>Qloo /search Seed UUIDs:</strong> ` +
        resolvedSeeds.map((s) => `${escapeHtml(s.name)} (<code>${escapeHtml(String(s.entity_id).slice(0, 8))}</code>)`).join(', ')
      : '';
  }

  if (els.plannedQlooUrnsList) {
    const plannedUrns = offers.map((o) => o.category_urn);
    els.plannedQlooUrnsList.innerHTML = plannedUrns.length
      ? `<strong>LLM Planned URNs:</strong> <code>${escapeHtml(plannedUrns.join(', '))}</code>`
      : '';
  }
}

/* ==========================================================================
   REAL-TIME PAUSE-TO-OFFER FRAME CAPTURE (<canvas>) + LIVE LLM & QLOO CALL
   ========================================================================== */
function capturePausedFrameBase64() {
  const videoEl = els.detailVideoPlayer;
  const canvas = els.frameCaptureCanvas;
  if (!videoEl || !canvas || !videoEl.videoWidth || !videoEl.videoHeight) {
    return null;
  }
  try {
    const targetW = 480;
    const targetH = Math.round((videoEl.videoHeight / videoEl.videoWidth) * targetW) || 270;
    canvas.width = targetW;
    canvas.height = targetH;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(videoEl, 0, 0, targetW, targetH);
    const dataUri = canvas.toDataURL('image/jpeg', 0.72);
    state.lastCapturedFrameDataUri = dataUri;
    return dataUri;
  } catch (e) {
    return null;
  }
}

async function triggerScenePulseAnalysis(overrideSeconds = null, forceLive = false) {
  const video = getActiveVideo();
  if (!video) return;

  const isCustom = Boolean(state.customVideoObjectUrl);
  const effectiveForceLive = isCustom ? true : forceLive;

  const currentSec =
    overrideSeconds !== null
      ? Number(overrideSeconds)
      : Math.floor(els.detailVideoPlayer?.currentTime || 5);

  els.detailCaptureStatusText.textContent = effectiveForceLive
    ? `Capturing T=${currentSec}s frame -> Executing live Gemma-3-27B Vision planner + live Qloo /search & /v2/insights...`
    : `Loading T=${currentSec}s scene telemetry...`;

  const pendingOverlay = document.getElementById('videoAnalysisPendingOverlay');
  const pendingTimerEl = document.getElementById('pendingElapsedTimer');
  const step1El = document.getElementById('pendingStep1');
  const step2El = document.getElementById('pendingStep2');
  const step3El = document.getElementById('pendingStep3');
  const rightCardEl = document.querySelector('.postalamak-card');

  const origCaptureBtnHtml = els.btnCaptureSceneNow ? els.btnCaptureSceneNow.innerHTML : '';
  const origForceBtnHtml = els.btnForceLiveRefresh ? els.btnForceLiveRefresh.innerHTML : '';

  let pendingTimerInterval = null;
  const startMs = performance.now();

  if (pendingOverlay) {
    pendingOverlay.classList.remove('hidden');
  }
  if (step1El && step2El && step3El) {
    step1El.className = 'pending-step-item is-active';
    step2El.className = 'pending-step-item';
    step3El.className = 'pending-step-item';
  }
  if (els.fourAlignedAdsGrid) {
    els.fourAlignedAdsGrid.classList.add('cards-pending-shimmer');
  }
  if (rightCardEl) {
    rightCardEl.classList.add('cards-pending-shimmer');
  }
  if (els.fourAdsSourceBadge) {
    els.fourAdsSourceBadge.textContent = 'PENDING · VISION + QLOO PIPELINE...';
  }

  els.btnForceLiveRefresh?.classList.add('is-analyzing-pulse');
  els.btnCaptureSceneNow?.classList.add('is-analyzing-pulse');

  if (els.btnCaptureSceneNow) {
    els.btnCaptureSceneNow.innerHTML = `<span class="btn-inline-spinner"></span><span>Analyzing Frame &amp; Querying Qloo...</span><span class="cta-live-pill">PENDING</span>`;
  }
  if (els.btnForceLiveRefresh) {
    els.btnForceLiveRefresh.innerHTML = `<span class="btn-inline-spinner"></span><span>Analyzing Frame &amp; Querying Qloo...</span><span class="cta-live-pill">PENDING</span>`;
  }

  pendingTimerInterval = window.setInterval(() => {
    const elapsedSec = ((performance.now() - startMs) / 1000).toFixed(1);
    if (pendingTimerEl) {
      pendingTimerEl.textContent = `${elapsedSec}s`;
    }
    const elapsedNum = parseFloat(elapsedSec);
    if (elapsedNum >= 0.4 && elapsedNum < 1.3 && step1El && step2El) {
      step1El.className = 'pending-step-item is-done';
      step2El.className = 'pending-step-item is-active';
    } else if (elapsedNum >= 1.3 && step2El && step3El) {
      step1El.className = 'pending-step-item is-done';
      step2El.className = 'pending-step-item is-done';
      step3El.className = 'pending-step-item is-active';
    }
  }, 100);

  const frameBase64 = capturePausedFrameBase64();
  const aSignals = isCustom
    ? []
    : Array.isArray(video.default_audio_signals)
    ? video.default_audio_signals
    : [];

  try {
    const res = await fetch('/api/v1/scenes/analyze', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        video_id: isCustom ? null : video.id,
        video_slug: isCustom ? null : video.slug,
        timestamp_seconds: currentSec,
        frame_image_base64: frameBase64,
        audio_10s_summary: aSignals.join(' | '),
        custom_video_name: state.customVideoName || null,
        force_refresh: effectiveForceLive,
      }),
    });

    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const payload = await res.json();

    if (payload.impulse) {
      state.activeImpulse = payload.impulse;
      state.selectedOfferIndex = 0;

      if (!isCustom && Array.isArray(video.impulses)) {
        const idx = video.impulses.findIndex((i) => i.time_bucket === payload.impulse.time_bucket);
        if (idx >= 0) {
          video.impulses[idx] = payload.impulse;
        } else {
          video.impulses.push(payload.impulse);
        }
      }

      const sourceBadge = effectiveForceLive
        ? `LIVE VISION + QLOO API (${payload.impulse.latency_ms || 0} ms)`
        : payload.cached_shield_active
        ? `5S BUCKET CACHE (Hit #${payload.hit_count})`
        : `LIVE QLOO API (${payload.impulse.latency_ms || 0} ms)`;

      els.fourAdsSourceBadge.textContent = sourceBadge;
      els.detailCaptureStatusText.textContent = `Frame T=${payload.impulse.timestamp_formatted || '00:05'} analyzed · 4 contextual Qloo /v2/insights entities updated.`;

      renderStageScreen();
      renderDetailScreen(false);
    }
  } catch (err) {
    console.error('Scene analysis error:', err);
    showToast('An error occurred during live scene analysis.');
  } finally {
    if (pendingTimerInterval) {
      window.clearInterval(pendingTimerInterval);
    }
    if (pendingOverlay) {
      pendingOverlay.classList.add('hidden');
    }
    if (els.fourAlignedAdsGrid) {
      els.fourAlignedAdsGrid.classList.remove('cards-pending-shimmer');
    }
    if (rightCardEl) {
      rightCardEl.classList.remove('cards-pending-shimmer');
    }
    if (els.btnCaptureSceneNow && origCaptureBtnHtml) {
      els.btnCaptureSceneNow.innerHTML = origCaptureBtnHtml;
    }
    if (els.btnForceLiveRefresh && origForceBtnHtml) {
      els.btnForceLiveRefresh.innerHTML = origForceBtnHtml;
    }
    els.btnForceLiveRefresh?.classList.remove('is-analyzing-pulse');
    els.btnCaptureSceneNow?.classList.remove('is-analyzing-pulse');
  }
}

/* ==========================================================================
   LIVE QLOO EXPLORER (/api/v1/qloo/explore)
   ========================================================================== */
async function runLiveQlooExplorer() {
  const query = (els.qlooExploreQueryInput?.value || 'Espresso').trim();
  const domain = els.qlooExploreTypeSelect?.value || 'brand';

  els.qlooExploreResultBox.innerHTML = `Querying live Qloo <code>/search</code> and <code>/v2/insights</code> endpoints for seed <strong>"${escapeHtml(query)}"</strong>...`;

  try {
    const res = await fetch(`/api/v1/qloo/explore?q=${encodeURIComponent(query)}&domain=${encodeURIComponent(domain)}`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const payload = await res.json();
    const data = payload.data || {};
    const searchEntities = Array.isArray(data.resolved_seeds) ? data.resolved_seeds : [];
    const insights = Array.isArray(data.insights) ? data.insights : [];

    const seedLabel = searchEntities[0]
      ? `${escapeHtml(searchEntities[0].name)} (UUID: <code>${escapeHtml(searchEntities[0].entity_id)}</code>)`
      : escapeHtml(query);

    const insightPills = insights
      .slice(0, 5)
      .map(
        (item) =>
          `<span class="cat-chip" style="background:#FFFFFF; color:#14141F; font-weight:600;" title="UUID: ${escapeHtml(item.entity_id)}">${escapeHtml(
            item.name
          )} (${escapeHtml(item.affinity_score)}%)</span>`
      )
      .join(' ');

    els.qlooExploreResultBox.innerHTML = `
      <div style="display:flex; flex-direction:column; gap:6px;">
        <div><strong>1. Qloo <code>/search</code> Resolved Seed:</strong> ${seedLabel}</div>
        <div style="display:flex; flex-wrap:wrap; gap:6px; align-items:center;">
          <strong>2. Qloo <code>/v2/insights</code> Matches (${escapeHtml(data.target_urn || '')}):</strong> ${insightPills || 'No matching entities returned.'}
        </div>
      </div>
    `;
  } catch (err) {
    console.error('Qloo explore error:', err);
    els.qlooExploreResultBox.textContent = 'Could not complete live Qloo API query.';
  }
}

/* ==========================================================================
   VIEW SWITCHING & EVENT BINDINGS
   ========================================================================== */
function switchView(viewName) {
  state.activeView = viewName;

  els.screenStage?.classList.add('hidden');
  els.screenDetail?.classList.add('hidden');
  els.screenHowItWorks?.classList.add('hidden');

  els.btnViewStage?.classList.remove('active');
  els.btnViewDetail?.classList.remove('active');
  els.btnViewHow?.classList.remove('active');

  if (viewName === 'detail') {
    els.screenDetail?.classList.remove('hidden');
    els.btnViewDetail?.classList.add('active');
    if (els.remotionWalkthroughVideo && !els.remotionWalkthroughVideo.paused) {
      els.remotionWalkthroughVideo.pause();
    }
    renderDetailScreen(true);
  } else if (viewName === 'how') {
    els.screenHowItWorks?.classList.remove('hidden');
    els.btnViewHow?.classList.add('active');
    if (els.detailVideoPlayer && !els.detailVideoPlayer.paused) {
      els.detailVideoPlayer.pause();
    }
    if (els.bgEditorialWatermark) {
      els.bgEditorialWatermark.textContent = 'PIPELINE';
    }
    if (els.remotionWalkthroughVideo) {
      els.remotionWalkthroughVideo.play().catch(() => {});
    }
  } else {
    els.screenStage?.classList.remove('hidden');
    els.btnViewStage?.classList.add('active');
    if (els.detailVideoPlayer && !els.detailVideoPlayer.paused) {
      els.detailVideoPlayer.pause();
    }
    if (els.remotionWalkthroughVideo && !els.remotionWalkthroughVideo.paused) {
      els.remotionWalkthroughVideo.pause();
    }
    renderStageScreen();
  }
}

function updateMuteButtonUI() {
  if (!els.detailVideoPlayer || !els.btnToggleMute) return;
  els.btnToggleMute.textContent = els.detailVideoPlayer.muted ? 'Audio: Muted' : 'Audio: On';
}

function startWaveformAnimation() {
  if (!els.leftWaveformBars) return;
  const bars = els.leftWaveformBars.querySelectorAll('.waveform-bar');
  state.waveformTimer = setInterval(() => {
    bars.forEach((bar) => {
      const h = Math.floor(24 + Math.random() * 72);
      bar.style.height = `${h}%`;
    });
  }, 260);
}

function bindEvents() {
  els.brandHomeTrigger?.addEventListener('click', () => switchView('stage'));
  els.btnViewStage?.addEventListener('click', () => switchView('stage'));
  els.btnViewDetail?.addEventListener('click', () => switchView('detail'));
  els.btnViewHow?.addEventListener('click', () => switchView('how'));
  els.btnStageOpenDetail?.addEventListener('click', () => switchView('detail'));
  els.btnStageOpenHow?.addEventListener('click', () => switchView('how'));
  els.btnBackToStage?.addEventListener('click', () => {
    if (state.customVideoObjectUrl) {
      selectVideoByIndex(state.activeVideoIndex, 5);
    }
    switchView('stage');
  });
  els.btnHowToStudio?.addEventListener('click', () => switchView('detail'));
  els.btnHowToStage?.addEventListener('click', () => switchView('stage'));

  // Remotion Walkthrough Chapter Jump Buttons
  document.querySelectorAll('.remotion-chapter-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.remotion-chapter-btn').forEach((b) => b.classList.remove('primary-dark'));
      btn.classList.add('primary-dark');
      const sec = Number(btn.getAttribute('data-time') || 0);
      if (els.remotionWalkthroughVideo) {
        els.remotionWalkthroughVideo.currentTime = sec;
        els.remotionWalkthroughVideo.play().catch(() => {});
      }
    });
  });

  // Stage time-bucket buttons
  document.querySelectorAll('.stage-bucket-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.stage-bucket-btn').forEach((b) => b.classList.remove('primary-dark'));
      btn.classList.add('primary-dark');
      const sec = Number(btn.getAttribute('data-bucket') || 5);
      triggerScenePulseAnalysis(sec, false);
    });
  });

  // Detail time-bucket buttons
  document.querySelectorAll('.detail-bucket-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.detail-bucket-btn').forEach((b) => b.classList.remove('primary-dark'));
      btn.classList.add('primary-dark');
      const sec = Number(btn.getAttribute('data-bucket') || 5);
      if (els.detailVideoPlayer) {
        els.detailVideoPlayer.currentTime = sec;
        els.detailVideoPlayer.pause();
      }
      setTimeout(() => triggerScenePulseAnalysis(sec, false), 120);
    });
  });

  // Video Player Controls in Screen 2
  els.btnPlayPauseVideo?.addEventListener('click', () => {
    const v = els.detailVideoPlayer;
    if (!v) return;
    if (v.paused) {
      v.play().catch(() => {});
      els.btnPlayPauseVideo.textContent = '⏸ Pause & Capture Frame';
    } else {
      v.pause();
      els.btnPlayPauseVideo.textContent = '▶ Play Video';
      triggerScenePulseAnalysis(null, false);
    }
  });

  els.detailVideoPlayer?.addEventListener('pause', () => {
    if (els.btnPlayPauseVideo) {
      els.btnPlayPauseVideo.textContent = '▶ Play Video';
    }
    capturePausedFrameBase64();
    if (els.capturedFramePreviewImg && state.lastCapturedFrameDataUri) {
      els.capturedFramePreviewImg.src = state.lastCapturedFrameDataUri;
    }
  });

  els.detailVideoPlayer?.addEventListener('play', () => {
    if (els.btnPlayPauseVideo) {
      els.btnPlayPauseVideo.textContent = '⏸ Pause & Capture Frame';
    }
  });

  els.btnCaptureSceneNow?.addEventListener('click', () => {
    if (els.detailVideoPlayer && !els.detailVideoPlayer.paused) {
      els.detailVideoPlayer.pause();
    }
    triggerScenePulseAnalysis(null, true);
    showToast('Live video frame (<canvas>) captured -> Running LLM URN planner + Qloo API.');
  });

  els.btnToggleMute?.addEventListener('click', () => {
    if (!els.detailVideoPlayer) return;
    els.detailVideoPlayer.muted = !els.detailVideoPlayer.muted;
    updateMuteButtonUI();
  });

  // Copy Qloo Proof JSON
  els.btnCopyOfferText?.addEventListener('click', () => {
    const offers = state.activeImpulse?.ad_offers || [];
    const selected = offers[state.selectedOfferIndex] || offers[0];
    if (!selected) return;
    const proof = JSON.stringify(
      {
        qloo_entity_name: selected.qloo_entity_name,
        qloo_entity_id: selected.qloo_entity_id,
        category_urn: selected.category_urn,
        qloo_affinity_score: selected.qloo_affinity_score,
        why_this_ad: selected.why_this_ad,
        resolved_seeds: state.activeImpulse?.qloo_resolved_entities || [],
        planned_urns: state.activeImpulse?.metrics?.planned_urns || [],
      },
      null,
      2
    );
    navigator.clipboard?.writeText(proof).catch(() => {});
    showToast(`Copied Qloo JSON proof to clipboard: ${selected.qloo_entity_name}`);
  });

  els.selectedOfferCoupon?.addEventListener('click', () => {
    const code = els.selectedOfferCoupon.textContent.trim();
    navigator.clipboard?.writeText(code).catch(() => {});
    showToast(`Copied reference code: ${code}`);
  });

  els.btnForceLiveRefresh?.addEventListener('click', () => {
    if (els.detailVideoPlayer && !els.detailVideoPlayer.paused) {
      els.detailVideoPlayer.pause();
    }
    triggerScenePulseAnalysis(null, true);
    showToast('Executing live LLM schema planner + Qloo /v2/insights call...');
  });

  // Live Qloo Explorer button
  els.btnRunQlooExplore?.addEventListener('click', () => {
    runLiveQlooExplorer();
  });

  els.qlooExploreQueryInput?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      runLiveQlooExplorer();
    }
  });

  // Custom MP4/WebM Upload
  els.btnUploadCustomVideo?.addEventListener('click', () => {
    els.customVideoInput?.click();
  });

  els.customVideoInput?.addEventListener('change', (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    if (state.customVideoObjectUrl) {
      URL.revokeObjectURL(state.customVideoObjectUrl);
    }
    state.customVideoObjectUrl = URL.createObjectURL(file);
    state.customVideoName = file.name.replace(/\.[^.]+$/, '');
    state.activeImpulse = null;
    state.lastCapturedFrameDataUri = null;
    state.selectedOfferIndex = 0;

    if (els.detailCaptureStatusText) {
      els.detailCaptureStatusText.textContent =
        'Custom video loaded — Click "⚡ Capture Live Frame & Run Qloo AI" to analyze the current frame.';
    }
    if (els.fourAdsSourceBadge) {
      els.fourAdsSourceBadge.textContent = 'READY FOR VISION + QLOO';
    }

    switchView('detail');
    if (els.detailVideoPlayer) {
      els.detailVideoPlayer.src = state.customVideoObjectUrl;
      els.detailVideoPlayer.poster = '';
      els.detailVideoPlayer.play().catch(() => {});
    }
    showToast(`Loaded "${file.name}". Click "⚡ Capture Live Frame" at any scene to run Vision LLM + Qloo AI.`);
    e.target.value = '';
  });

  // Keyboard navigation shortcuts
  window.addEventListener('keydown', (e) => {
    const tag = document.activeElement?.tagName?.toLowerCase();
    if (tag === 'input' || tag === 'textarea' || tag === 'select') return;

    if (state.activeView === 'stage') {
      if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
        e.preventDefault();
        selectVideoByIndex(state.activeVideoIndex + 1, 5);
      } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
        e.preventDefault();
        selectVideoByIndex(state.activeVideoIndex - 1, 5);
      } else if (e.key === 'Enter') {
        e.preventDefault();
        switchView('detail');
      }
    }
  });
}

document.addEventListener('DOMContentLoaded', initApp);
