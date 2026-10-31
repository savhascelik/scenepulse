import React from 'react';
import {
  AbsoluteFill,
  Img,
  interpolate,
  Sequence,
  spring,
  useCurrentFrame,
  useVideoConfig,
} from 'remotion';

/**
 * ScenePulse by Flownie — Remotion Architecture Explainer Composition (Light Editorial Theme)
 *
 * Palette (Zero Dark Mode / Zero Gradients / Zero Emojis):
 * - Paper Canvas: #F7F6F2
 * - Surface Card: #FFFFFF
 * - Hairline Border: #E3E1DA
 * - Primary Ink: #14141F
 * - Secondary Slate: #565869
 * - Editorial Accent: #1F4E5B
 */

export const SCENEPULSE_REMOTION_CONFIG = {
  id: 'ScenePulseHowItWorks',
  fps: 30,
  durationInFrames: 720, // 24 seconds @ 30fps (4 chapters x 6 seconds)
  width: 1280,
  height: 720,
};

const PALETTE = {
  bg: '#F7F6F2',
  surface: '#FFFFFF',
  surfaceMuted: '#EFECE6',
  border: '#DFDDD5',
  ink: '#14141F',
  slate: '#565869',
  muted: '#858796',
  accent: '#1F4E5B',
  emerald: '#0F6E56',
};

interface StepCardProps {
  stepNumber: string;
  kicker: string;
  title: string;
  description: string;
  codeBlock: string;
  active: boolean;
}

const StepModule: React.FC<StepCardProps> = ({
  stepNumber,
  kicker,
  title,
  description,
  codeBlock,
  active,
}) => {
  return (
    <div
      style={{
        backgroundColor: PALETTE.surface,
        border: `1.5px solid ${active ? PALETTE.ink : PALETTE.border}`,
        borderRadius: 16,
        padding: 24,
        display: 'flex',
        flexDirection: 'column',
        justifyContent: 'space-between',
        boxShadow: active
          ? '0 18px 40px -12px rgba(20, 20, 31, 0.10)'
          : '0 4px 12px rgba(20, 20, 31, 0.03)',
      }}
    >
      <div>
        <div
          style={{
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center',
            marginBottom: 10,
            fontFamily: 'JetBrains Mono, monospace',
            fontSize: 12,
            color: active ? PALETTE.ink : PALETTE.muted,
          }}
        >
          <span>STEP {stepNumber}</span>
          <span
            style={{
              padding: '3px 8px',
              borderRadius: 4,
              backgroundColor: PALETTE.surfaceMuted,
              color: PALETTE.ink,
              fontWeight: 600,
            }}
          >
            {kicker}
          </span>
        </div>
        <h3
          style={{
            fontFamily: 'Instrument Serif, Georgia, serif',
            fontSize: 28,
            fontWeight: 400,
            color: PALETTE.ink,
            margin: '0 0 8px 0',
          }}
        >
          {title}
        </h3>
        <p
          style={{
            fontFamily: 'Inter, sans-serif',
            fontSize: 13.5,
            lineHeight: 1.5,
            color: PALETTE.slate,
            margin: 0,
          }}
        >
          {description}
        </p>
      </div>

      <pre
        style={{
          marginTop: 16,
          padding: 12,
          borderRadius: 8,
          backgroundColor: PALETTE.bg,
          border: `1px solid ${PALETTE.border}`,
          fontFamily: 'JetBrains Mono, monospace',
          fontSize: 11.5,
          color: PALETTE.ink,
          overflow: 'hidden',
          whiteSpace: 'pre-wrap',
        }}
      >
        {codeBlock}
      </pre>
    </div>
  );
};

export const ScenePulseHowItWorks: React.FC = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  const activeStage = Math.min(3, Math.floor(frame / (fps * 6)));
  const progressPct = interpolate(frame, [0, 720], [0, 100], {
    extrapolateRight: 'clamp',
  });

  const fadeIn = spring({
    frame: frame % (fps * 6),
    fps,
    config: { damping: 18, stiffness: 120 },
  });

  return (
    <AbsoluteFill
      style={{
        backgroundColor: PALETTE.bg,
        color: PALETTE.ink,
        padding: '44px 56px',
        fontFamily: 'Inter, sans-serif',
        display: 'flex',
        flexDirection: 'column',
        justifyContent: 'space-between',
      }}
    >
      {/* Top Editorial Header */}
      <div
        style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          borderBottom: `1px solid ${PALETTE.border}`,
          paddingBottom: 20,
        }}
      >
        <div>
          <div
            style={{
              fontFamily: 'JetBrains Mono, monospace',
              fontSize: 12,
              letterSpacing: '0.08em',
              color: PALETTE.slate,
              textTransform: 'uppercase',
            }}
          >
            SCENEPULSE BY FLOWNIE · REMOTION ARCHITECTURE WALKTHROUGH
          </div>
          <h1
            style={{
              fontFamily: 'Instrument Serif, Georgia, serif',
              fontSize: 42,
              fontWeight: 400,
              margin: '4px 0 0 0',
            }}
          >
            From Paused Video Frame &amp; 10s Audio to Dynamic Qloo Taste AI
          </h1>
        </div>
        <div
          style={{
            padding: '8px 14px',
            borderRadius: 6,
            backgroundColor: PALETTE.surface,
            border: `1px solid ${PALETTE.border}`,
            fontFamily: 'JetBrains Mono, monospace',
            fontSize: 12,
          }}
        >
          PHASE 0{activeStage + 1} / 04 · {Math.round(progressPct)}%
        </div>
      </div>

      {/* 4-Stage Pipeline Grid */}
      <Sequence from={0}>
        <div
          style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(4, 1fr)',
            gap: 20,
            marginTop: 24,
            marginBottom: 24,
            flex: 1,
            opacity: interpolate(fadeIn, [0, 1], [0.92, 1]),
          }}
        >
          <StepModule
            stepNumber="01"
            kicker="HTML5 CANVAS + AUDIO"
            title="Frame & 10s Audio Capture"
            description="When a viewer pauses or scrubs a scene, ScenePulse captures the exact video frame via <canvas> and the trailing 10-second audio telemetry."
            codeBlock={`canvas.drawImage(video, 0, 0, 480, 270);\nframeBase64 = canvas.toDataURL('image/jpeg');\naudioWindow = extractLast10sAudio(video);`}
            active={activeStage === 0}
          />
          <StepModule
            stepNumber="02"
            kicker="DYNAMIC URN PLANNER"
            title="LLM Qloo Schema Planner"
            description="The multimodal LLM receives the frame, 10s audio, and the full Qloo API catalog, selecting only the 4 most relevant Qloo /v2/insights URNs for the scene."
            codeBlock={`// Coffee Scene -> No videogame URN!\nplanned_urns: [\n  "urn:entity:brand",\n  "urn:entity:place",\n  "urn:entity:artist",\n  "urn:entity:book"\n]`}
            active={activeStage === 1}
          />
          <StepModule
            stepNumber="03"
            kicker="GET /SEARCH"
            title="Qloo Seed UUID Resolution"
            description="Concrete entities detected in the scene (e.g. Blue Bottle Coffee, Espresso, Kyoto, Andy McKee) are resolved into official Qloo entity UUIDs."
            codeBlock={`GET https://hackathon.api.qloo.com/search\n?query=Blue+Bottle+Coffee\n-> entity_id: "74E1626C-6A1E-4A0C..."`}
            active={activeStage === 2}
          />
          <StepModule
            stepNumber="04"
            kicker="GET /V2/INSIGHTS"
            title="Cross-Domain Taste Output"
            description="Qloo /v2/insights computes high-affinity cultural matches across the 4 LLM-selected URNs, cached in a 5-second SQLite time-bucket."
            codeBlock={`GET /v2/insights?filter.type=urn:entity:brand\n&signal.interests.entities={seed_uuids}\n-> 96.4% Cultural Affinity Match`}
            active={activeStage === 3}
          />
        </div>
      </Sequence>

      {/* Bottom Progress Bar */}
      <div
        style={{
          height: 6,
          backgroundColor: PALETTE.surfaceMuted,
          borderRadius: 999,
          overflow: 'hidden',
        }}
      >
        <div
          style={{
            width: `${progressPct}%`,
            height: '100%',
            backgroundColor: PALETTE.ink,
          }}
        />
      </div>
    </AbsoluteFill>
  );
};
