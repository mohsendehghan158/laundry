"use client";

import { useCallback, useRef } from "react";

type Tone = { frequency: number; duration: number };

const SOUNDS: Record<"ok" | "warn" | "error", Tone[]> = {
  ok: [{ frequency: 1320, duration: 0.09 }],
  warn: [
    { frequency: 880, duration: 0.1 },
    { frequency: 1100, duration: 0.12 },
  ],
  error: [
    { frequency: 220, duration: 0.16 },
    { frequency: 180, duration: 0.24 },
  ],
};

/**
 * Short beeps for scan feedback, so an operator does not have to look at
 * the screen: one high beep for done, two rising for a warning, two low
 * for a refusal. Silently does nothing where audio is unavailable.
 */
export function useFeedbackSound() {
  const context = useRef<AudioContext | null>(null);

  return useCallback((kind: keyof typeof SOUNDS) => {
    try {
      context.current ??= new AudioContext();
      const audio = context.current;
      let at = audio.currentTime;
      for (const tone of SOUNDS[kind]) {
        const oscillator = audio.createOscillator();
        const gain = audio.createGain();
        oscillator.type = "square";
        oscillator.frequency.value = tone.frequency;
        gain.gain.setValueAtTime(0.08, at);
        gain.gain.exponentialRampToValueAtTime(0.0001, at + tone.duration);
        oscillator.connect(gain).connect(audio.destination);
        oscillator.start(at);
        oscillator.stop(at + tone.duration);
        at += tone.duration + 0.03;
      }
    } catch {
      // No audio (tests, locked-down browsers): visual feedback is enough.
    }
  }, []);
}
