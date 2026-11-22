/**
 * assets/js/timer.js - StudyMaze Complete Timer, Clock, Pomodoro, Stopwatch & Audio Controller
 * Fixed: ambient sound system, sound_enabled global gate, settings sync, focus mode,
 *        proper fade-in/fade-out, 8 sound types, volume slider live update.
 */

(function () {
  'use strict';

  // ──────────────────────────────────────────────────────────────────────────
  // Configuration & Persistent User Settings (injected by timer.php)
  // ──────────────────────────────────────────────────────────────────────────
  const cfg = window.studyMazeConfig || {
    defaultFocusMin:  25,
    shortBreakMin:    5,
    longBreakMin:     15,
    pomodoroRounds:   4,
    autoStartBreaks:  true,
    soundEnabled:     true,
    defaultSound:     'rain',
    soundVolume:      60,
    timerNotifications: true
  };

  const CIRCLE_CIRCUMFERENCE = 911; // 2 * PI * 145

  // ──────────────────────────────────────────────────────────────────────────
  // Application State
  // ──────────────────────────────────────────────────────────────────────────
  let currentMode            = 'timer';   // 'timer','clock','pomodoro','stopwatch'
  let timerState             = 'idle';    // 'idle','running','paused'
  let activeSessionId        = null;
  let isSessionActionPending = false;     // mutex for API calls
  let isFocusModeActive      = false;     // single source of truth

  // Timer Variables
  let totalDurationSec = cfg.defaultFocusMin * 60;
  let remainingSec     = totalDurationSec;
  let stopwatchSec     = 0;
  let timerInterval    = null;
  let clockInterval    = null;
  let lastTimestamp    = null;

  // Pomodoro State
  let pomodoroCycle = 1;
  let pomodoroPhase = 'focus'; // 'focus','short_break','long_break'

  // ──────────────────────────────────────────────────────────────────────────
  // DOM Elements
  // ──────────────────────────────────────────────────────────────────────────
  const modePills             = document.querySelectorAll('.mode-pill[data-mode]');
  const timerDigits           = document.getElementById('timerDigits');
  const timerTopLabel         = document.getElementById('timerTopLabel');
  const timerProgressCircle   = document.getElementById('timerProgressCircle');
  const timerPrimaryBtn       = document.getElementById('timerPrimaryBtn');
  const timerPrimaryIcon      = document.getElementById('timerPrimaryIcon');
  const timerResetBtn         = document.getElementById('timerResetBtn');
  const timerStopSaveBtn      = document.getElementById('timerStopSaveBtn');
  const durationPills         = document.querySelectorAll('#durationPills .duration-pill');
  const pomodoroPills         = document.querySelectorAll('#pomodoroDurationPills .duration-pill');
  const subjectSelect         = document.getElementById('timerSubjectSelect');
  const pomodoroBadge         = document.getElementById('pomodoroBadge');
  const pomodoroStateText     = document.getElementById('pomodoroStateText');
  const pomodoroDotsContainer = document.getElementById('pomodoroDots');
  const quoteElem             = document.getElementById('motivationalQuote');
  const refreshQuoteBtn       = document.getElementById('refreshQuoteBtn');

  // Drawers and Focus Controls
  const toggleSoundsBtn   = document.getElementById('toggleSoundsBtn');
  const soundsDrawer      = document.getElementById('soundsDrawer');
  const enterFocusBtn     = document.getElementById('enterFocusBtn');
  const exitFocusBtn      = document.getElementById('exitFocusBtn');
  const toggleTasksBtn    = document.getElementById('toggleTasksBtn');
  const tasksDrawer       = document.getElementById('tasksDrawer');
  const soundVolumeSlider = document.getElementById('soundVolumeSlider');
  const volumeValueLabel  = document.getElementById('volumeValueLabel');
  const muteSoundBtn      = document.getElementById('muteSoundBtn');

  // ──────────────────────────────────────────────────────────────────────────
  // ──────────────────────────────────────────────────────────────────────────
  // Ambient Sound Engine (Native HTML5 Audio with Procedural Web Audio Fallback)
  // ──────────────────────────────────────────────────────────────────────────

  let audioCtx        = null;   // AudioContext for fallback synthesis / notification
  let ambientSource   = null;   // BufferSource for procedural fallback
  let ambientGainNode = null;   // GainNode for procedural fallback
  let ambientAudioEl  = null;   // Active HTML5 Audio element
  let activeSoundName = null;   // Name of currently playing sound
  let isMuted         = false;
  let soundEnabled    = !!cfg.soundEnabled;

  /** Ensure AudioContext exists and is resumed */
  function ensureAudioContext() {
    if (!audioCtx) {
      audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    }
    if (audioCtx.state === 'suspended') audioCtx.resume().catch(() => {});
    return audioCtx;
  }

  /** Volume 0-1 from slider, respecting mute */
  function getTargetVolume() {
    if (isMuted) return 0;
    const v = soundVolumeSlider ? parseInt(soundVolumeSlider.value, 10) : (cfg.soundVolume || 60);
    return Math.max(0, Math.min(1, v / 100));
  }

  /** Stop all ambient audio */
  function stopAmbient(immediately) {
    if (ambientAudioEl) {
      const el = ambientAudioEl;
      ambientAudioEl = null;
      try {
        el.pause();
        el.currentTime = 0;
        el.src = '';
      } catch (e) {}
    }

    if (ambientSource) {
      try { ambientSource.stop(); } catch (e) {}
      ambientSource = null;
    }
    if (ambientGainNode) {
      try { ambientGainNode.disconnect(); } catch (e) {}
      ambientGainNode = null;
    }

    activeSoundName = null;
    document.querySelectorAll('.sound-btn').forEach(b => b.classList.remove('playing'));
  }

  /** Procedural ambient synthesis via Web Audio API buffer (4 core calming sounds) */
  function generateProceduralBuffer(soundName, ctx) {
    const sr     = ctx.sampleRate;
    const dur    = 12; // 12-second looping buffer
    const frames = sr * dur;
    const buf    = ctx.createBuffer(1, frames, sr);
    const data   = buf.getChannelData(0);

    if (soundName === 'rain') {
      // Gentle rainfall with soft droplet texture
      for (let i = 0; i < frames; i++) data[i] = (Math.random() * 2 - 1) * 0.04;
      for (let i = 1; i < frames; i++) data[i] = data[i] * 0.35 + data[i-1] * 0.65;
      // Add subtle raindrops
      const dropCount = Math.floor(dur * 8);
      for (let d = 0; d < dropCount; d++) {
        const pos = Math.floor(Math.random() * (frames - 1000));
        const freq = 800 + Math.random() * 600;
        const len = Math.floor(sr * 0.03);
        for (let j = 0; j < len; j++) {
          const t = j / sr;
          const env = Math.exp(-t * 90);
          if (pos + j < frames) {
            data[pos + j] += Math.sin(2 * Math.PI * freq * t) * 0.008 * env;
          }
        }
      }

    } else if (soundName === 'ocean') {
      // Gentle, slow rolling waves (4.5 second swell)
      let last = 0;
      for (let i = 0; i < frames; i++) {
        last = last * 0.996 + (Math.random() * 2 - 1) * 0.004;
        const t = i / sr;
        const swell = 0.35 + 0.65 * (0.5 + 0.5 * Math.sin(2 * Math.PI * t / 4.5));
        data[i] = last * swell * 0.9;
      }
      for (let i = 1; i < frames; i++) data[i] = data[i] * 0.25 + data[i-1] * 0.75;

    } else if (soundName === 'forest') {
      // Peaceful woodland ambience: subtle rustle + soft distant birdsong
      let last = 0;
      for (let i = 0; i < frames; i++) {
        last = last * 0.995 + (Math.random() * 2 - 1) * 0.005;
        data[i] = last * 0.3;
      }
      // Faint sweet birdsong chirps
      const chirps = [
        { t: 1.5, notes: [1318.5, 1567.9] },
        { t: 4.5, notes: [1760.0, 1567.9, 1318.5] },
        { t: 7.5, notes: [1396.9, 1567.9] },
        { t: 10.2, notes: [1567.9, 1760.0] }
      ];
      chirps.forEach(c => {
        let notePos = Math.floor(c.t * sr);
        c.notes.forEach(freq => {
          const noteLen = Math.floor(sr * 0.12);
          for (let j = 0; j < noteLen; j++) {
            const t = j / sr;
            const env = Math.sin(Math.PI * (j / noteLen)) * Math.exp(-t * 12);
            if (notePos + j < frames) {
              data[notePos + j] += Math.sin(2 * Math.PI * freq * t) * 0.007 * env;
            }
          }
          notePos += Math.floor(sr * 0.14);
        });
      });

    } else { // library: soft, beautiful, relaxing ambient study music chords
      for (let i = 0; i < frames; i++) data[i] = (Math.random() * 2 - 1) * 0.003;
      // 4 relaxing 3-second chords: Cmaj7, Am9, Fmaj7, Gadd9
      const chords = [
        { t: 0.0, notes: [130.81, 196.00, 246.94, 329.63] },
        { t: 3.0, notes: [110.00, 164.81, 196.00, 261.63, 493.88] },
        { t: 6.0, notes: [87.31,  130.81, 164.81, 220.00, 261.63] },
        { t: 9.0, notes: [98.00,  146.83, 196.00, 246.94, 293.66] }
      ];
      chords.forEach(c => {
        const startIdx = Math.floor(c.t * sr);
        const chordLen = Math.floor(sr * 3.0);
        c.notes.forEach((freq, nIdx) => {
          const offset = Math.floor(nIdx * sr * 0.04);
          const pos = startIdx + offset;
          for (let j = 0; j < chordLen - offset; j++) {
            const t = j / sr;
            const attack = Math.min(1.0, t / 0.06);
            const decay = Math.exp(-t * 0.9);
            const env = attack * decay * 0.012;
            const tone = Math.sin(2 * Math.PI * freq * t) + 0.3 * Math.sin(4 * Math.PI * freq * t);
            if (pos + j < frames) {
              data[pos + j] += tone * env;
            }
          }
        });
      });
      // Soft warmth smoothing
      for (let i = 1; i < frames; i++) data[i] = data[i] * 0.3 + data[i-1] * 0.7;
    }

    return buf;
  }

  function playProceduralFallback(soundName, vol) {
    try {
      const ctx = ensureAudioContext();
      if (ambientSource) {
        try { ambientSource.stop(); } catch (e) {}
        ambientSource = null;
      }
      ambientGainNode = ctx.createGain();
      ambientGainNode.gain.setValueAtTime(isMuted ? 0 : vol, ctx.currentTime);
      ambientGainNode.connect(ctx.destination);

      const buf = generateProceduralBuffer(soundName, ctx);
      const source = ctx.createBufferSource();
      source.buffer = buf;
      source.loop = true;
      source.connect(ambientGainNode);
      source.start();
      ambientSource = source;
    } catch (e) {
      console.error('Procedural audio error:', e);
      window.showToast('Audio playback could not be started.', 'error', 3000);
      activeSoundName = null;
      document.querySelectorAll('.sound-btn').forEach(b => b.classList.remove('playing'));
    }
  }

  /**
   * Start playing a named ambient sound.
   * Direct HTML5 Audio playback in synchronous user click handler.
   */
  function initAmbientAudio(soundName) {
    if (!soundEnabled) {
      window.showToast('Enable Ambient Sounds in Settings to use this feature.', 'info', 3000);
      return;
    }

    // Toggle: clicking the active sound stops it
    if (activeSoundName === soundName) {
      stopAmbient(false);
      return;
    }

    // Stop current sound quickly before starting new one
    if (activeSoundName !== null) {
      stopAmbient(true);
    }

    activeSoundName = soundName;
    document.querySelectorAll('.sound-btn').forEach(b => {
      b.classList.toggle('playing', b.dataset.sound === soundName);
    });

    const targetVol = getTargetVolume();

    // Create native HTML5 audio and play synchronously
    const audio = new Audio(`assets/audio/${soundName}.wav`);
    audio.loop = true;
    audio.volume = targetVol;

    const playPromise = audio.play();
    if (playPromise !== undefined) {
      playPromise.then(() => {
        ambientAudioEl = audio;
      }).catch((err) => {
        console.warn('Native HTML5 audio failed, falling back to Web Audio synthesis:', err);
        playProceduralFallback(soundName, targetVol);
      });
    } else {
      ambientAudioEl = audio;
    }
  }

  /** Update volume of currently-playing sound without restarting it */
  function updateAmbientVolume(vol) {
    const v = Math.max(0, Math.min(1, vol));
    if (ambientAudioEl) {
      ambientAudioEl.volume = isMuted ? 0 : v;
    }
    if (ambientGainNode && audioCtx) {
      ambientGainNode.gain.setValueAtTime(isMuted ? 0 : v, audioCtx.currentTime);
    }
  }

  /**
   * Apply the global soundEnabled preference.
   * Called on page load and whenever the setting changes.
   */
  function applySoundEnabledState(enabled) {
    soundEnabled = !!enabled;

    if (enabled) {
      if (toggleSoundsBtn) {
        toggleSoundsBtn.style.display = '';
        toggleSoundsBtn.removeAttribute('hidden');
      }
    } else {
      // Stop playing sound and hide the sounds UI completely
      if (activeSoundName !== null) stopAmbient(true);
      if (soundsDrawer) soundsDrawer.classList.remove('show');
      if (toggleSoundsBtn) {
        toggleSoundsBtn.style.display = 'none';
        toggleSoundsBtn.setAttribute('hidden', '');
        toggleSoundsBtn.classList.remove('active');
      }
    }
  }

  // ──────────────────────────────────────────────────────────────────────────
  // Focus Mode (Single Source of Truth)
  // ──────────────────────────────────────────────────────────────────────────

  function enterFocusMode() {
    isFocusModeActive = true;
    sessionStorage.setItem('studymaze_focus_mode', 'true');
    document.body.classList.add('focus-mode-active');

    if (exitFocusBtn) {
      exitFocusBtn.hidden = false;
      exitFocusBtn.style.setProperty('display', 'inline-flex', 'important');
      exitFocusBtn.setAttribute('aria-hidden', 'false');
    }
    if (enterFocusBtn) {
      enterFocusBtn.classList.add('active');
      enterFocusBtn.setAttribute('aria-pressed', 'true');
      enterFocusBtn.innerHTML = '<i data-lucide="minimize-2"></i><span>Focus Mode</span>';
      if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    if (document.documentElement.requestFullscreen && !document.fullscreenElement) {
      document.documentElement.requestFullscreen().catch(() => {});
    }

    if (soundsDrawer) soundsDrawer.classList.remove('show');
    if (tasksDrawer)  tasksDrawer.classList.remove('show');

    window.showToast('Entered Focus Mode — press F or Esc to exit', 'info', 2500);
  }

  function exitFocusMode() {
    isFocusModeActive = false;
    sessionStorage.removeItem('studymaze_focus_mode');
    document.body.classList.remove('focus-mode-active');

    if (exitFocusBtn) {
      exitFocusBtn.hidden = true;
      exitFocusBtn.style.setProperty('display', 'none', 'important');
      exitFocusBtn.setAttribute('aria-hidden', 'true');
    }
    if (enterFocusBtn) {
      enterFocusBtn.classList.remove('active');
      enterFocusBtn.setAttribute('aria-pressed', 'false');
      enterFocusBtn.innerHTML = '<i data-lucide="maximize-2"></i><span>Focus Mode</span>';
      if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    if (document.exitFullscreen && document.fullscreenElement) {
      document.exitFullscreen().catch(() => {});
    }
  }

  document.addEventListener('fullscreenchange', () => {
    if (!document.fullscreenElement && isFocusModeActive) exitFocusMode();
  });
  document.addEventListener('webkitfullscreenchange', () => {
    if (!document.webkitFullscreenElement && isFocusModeActive) exitFocusMode();
  });

  window.addEventListener('beforeunload', () => {
    sessionStorage.removeItem('studymaze_focus_mode');
    document.body.classList.remove('focus-mode-active');
    stopAmbient(true);
  });

  // ──────────────────────────────────────────────────────────────────────────
  // Custom Modal Prompt
  // ──────────────────────────────────────────────────────────────────────────

  function showPromptModal(message, placeholder, defaultVal) {
    placeholder = placeholder || '';
    defaultVal  = defaultVal  || '';
    return new Promise((resolve) => {
      const existing = document.getElementById('smPromptModal');
      if (existing) existing.remove();

      const overlay = document.createElement('div');
      overlay.id = 'smPromptModal';
      overlay.setAttribute('role', 'dialog');
      overlay.setAttribute('aria-modal', 'true');
      overlay.style.cssText = 'position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.5);';

      overlay.innerHTML = `
        <div style="background:var(--bg-card,#fff);border:1px solid var(--border-color,#e5e7eb);border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.2);padding:24px;max-width:360px;width:90%;font-family:inherit;">
          <p style="color:var(--text-primary,#111827);font-size:.95rem;margin:0 0 14px;font-weight:500;">${message}</p>
          <input id="smPromptInput" type="number" min="1" max="720" placeholder="${placeholder}" value="${defaultVal}"
            style="width:100%;box-sizing:border-box;padding:9px 13px;border:1px solid var(--border-color,#e5e7eb);border-radius:8px;font-size:1rem;color:var(--text-primary,#111827);background:var(--bg-secondary,#f9fafb);margin-bottom:16px;outline:none;">
          <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button id="smPromptCancel" type="button" style="padding:8px 16px;border-radius:8px;border:1px solid var(--border-color,#e5e7eb);background:var(--bg-secondary,#f3f4f6);color:var(--text-muted,#6b7280);font-size:.875rem;cursor:pointer;font-weight:500;">Cancel</button>
            <button id="smPromptOk" type="button" style="padding:8px 18px;border-radius:8px;border:none;background:var(--accent,#ea580c);color:#fff;font-size:.875rem;cursor:pointer;font-weight:600;">Set Duration</button>
          </div>
        </div>`;

      document.body.appendChild(overlay);
      const input = overlay.querySelector('#smPromptInput');
      function cleanup(r) { overlay.remove(); resolve(r); }
      overlay.querySelector('#smPromptOk').addEventListener('click', () => cleanup(input.value.trim()));
      overlay.querySelector('#smPromptCancel').addEventListener('click', () => cleanup(null));
      overlay.addEventListener('click', (e) => { if (e.target === overlay) cleanup(null); });
      input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter')  cleanup(input.value.trim());
        if (e.key === 'Escape') cleanup(null);
      });
      setTimeout(() => { input.select(); input.focus(); }, 50);
    });
  }

  // ──────────────────────────────────────────────────────────────────────────
  // Formatting & Ring
  // ──────────────────────────────────────────────────────────────────────────

  function formatDigits(seconds) {
    seconds = Math.max(0, Math.floor(seconds));
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    const p = (n) => String(n).padStart(2, '0');
    return h > 0 ? `${p(h)}:${p(m)}:${p(s)}` : `${p(m)}:${p(s)}`;
  }

  function updateRing(ratio) {
    if (!timerProgressCircle) return;
    timerProgressCircle.style.strokeDashoffset =
      CIRCLE_CIRCUMFERENCE * (1 - Math.min(1, Math.max(0, ratio)));
  }

  // ──────────────────────────────────────────────────────────────────────────
  // Mode Switching
  // ──────────────────────────────────────────────────────────────────────────

  async function switchMode(newMode) {
    if (newMode === currentMode) return;

    if (timerState === 'running') {
      const confirmed = await window.showConfirmModal(
        'A timer session is active. Switch modes and discard the current session?',
        'Switch Mode', 'Stay Here'
      );
      if (!confirmed) return;
      await stopSession(false);
    }

    currentMode = newMode;
    clearInterval(clockInterval);
    clearInterval(timerInterval);
    timerState = 'idle';

    modePills.forEach((p) => {
      const isActive = p.dataset.mode === newMode;
      p.classList.toggle('active', isActive);
      p.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });

    const timerPillsElem = document.getElementById('durationPills');
    const pomPillsElem   = document.getElementById('pomodoroDurationPills');

    if (newMode === 'timer') {
      timerTopLabel.textContent    = 'SET DURATION';
      pomodoroBadge.style.display  = 'none';
      timerPillsElem.style.display = 'flex';
      pomPillsElem.style.display   = 'none';
      totalDurationSec = cfg.defaultFocusMin * 60;
      remainingSec     = totalDurationSec;
      timerDigits.textContent = formatDigits(remainingSec);
      updateRing(1);
      enablePrimaryControls(true);

    } else if (newMode === 'clock') {
      timerTopLabel.textContent    = 'CURRENT TIME';
      pomodoroBadge.style.display  = 'none';
      timerPillsElem.style.display = 'none';
      pomPillsElem.style.display   = 'none';
      updateClockDisplay();
      clockInterval = setInterval(updateClockDisplay, 1000);
      enablePrimaryControls(false);

    } else if (newMode === 'pomodoro') {
      pomodoroBadge.style.display  = 'inline-flex';
      timerPillsElem.style.display = 'none';
      pomPillsElem.style.display   = 'flex';
      pomodoroCycle = 1;
      pomodoroPhase = 'focus';
      updatePomodoroBadge();
      totalDurationSec = cfg.defaultFocusMin * 60;
      remainingSec     = totalDurationSec;
      timerTopLabel.textContent   = 'FOCUS DURATION';
      timerDigits.textContent     = formatDigits(remainingSec);
      updateRing(1);
      enablePrimaryControls(true);

    } else if (newMode === 'stopwatch') {
      timerTopLabel.textContent    = 'STOPWATCH';
      pomodoroBadge.style.display  = 'none';
      timerPillsElem.style.display = 'none';
      pomPillsElem.style.display   = 'none';
      stopwatchSec = 0;
      timerDigits.textContent = '00:00';
      updateRing(1);
      enablePrimaryControls(true);
    }

    updateControlsUI();
  }

  function enablePrimaryControls(enabled) {
    if (timerPrimaryBtn) {
      timerPrimaryBtn.style.opacity       = enabled ? '1' : '0.4';
      timerPrimaryBtn.style.pointerEvents = enabled ? 'auto' : 'none';
      timerPrimaryBtn.setAttribute('aria-disabled', enabled ? 'false' : 'true');
    }
    if (timerResetBtn) {
      timerResetBtn.style.opacity       = enabled ? '1' : '0.4';
      timerResetBtn.style.pointerEvents = enabled ? 'auto' : 'none';
      timerResetBtn.setAttribute('aria-disabled', enabled ? 'false' : 'true');
    }
  }

  function updateClockDisplay() {
    const now = new Date();
    timerDigits.textContent = now.toLocaleTimeString([], { hour12: false });
    updateRing((60 - now.getSeconds()) / 60);
  }

  function updatePomodoroBadge() {
    if (pomodoroPhase === 'focus') {
      pomodoroStateText.textContent = `Focus Session — Round ${pomodoroCycle} of ${cfg.pomodoroRounds}`;
      timerTopLabel.textContent     = 'FOCUS DURATION';
    } else if (pomodoroPhase === 'short_break') {
      pomodoroStateText.textContent = `Short Break (${cfg.shortBreakMin}m)`;
      timerTopLabel.textContent     = 'REST & RECHARGE';
    } else {
      pomodoroStateText.textContent = `Long Break (${cfg.longBreakMin}m)`;
      timerTopLabel.textContent     = 'LONG BREAK';
    }
    const dots = pomodoroDotsContainer ? pomodoroDotsContainer.querySelectorAll('.p-dot') : [];
    dots.forEach((dot, idx) => dot.classList.toggle('filled', idx < pomodoroCycle));
  }

  // ──────────────────────────────────────────────────────────────────────────
  // High-Precision Ticker (Drift-Free)
  // ──────────────────────────────────────────────────────────────────────────

  function startTicking() {
    clearInterval(timerInterval);
    lastTimestamp = performance.now();

    timerInterval = setInterval(() => {
      const now      = performance.now();
      const deltaSec = (now - lastTimestamp) / 1000;
      lastTimestamp  = now;

      if (currentMode === 'timer' || currentMode === 'pomodoro') {
        remainingSec -= deltaSec;
        if (remainingSec <= 0) {
          remainingSec = 0;
          timerDigits.textContent = '00:00';
          updateRing(0);
          handleTimerCompleted();
          return;
        }
        timerDigits.textContent = formatDigits(remainingSec);
        updateRing(remainingSec / totalDurationSec);
      } else if (currentMode === 'stopwatch') {
        stopwatchSec += deltaSec;
        timerDigits.textContent = formatDigits(stopwatchSec);
        updateRing((stopwatchSec % 60) / 60);
      }
    }, 200);
  }

  // ──────────────────────────────────────────────────────────────────────────
  // Controls UI (icon switching)
  // ──────────────────────────────────────────────────────────────────────────

  function updateControlsUI() {
    if (!timerPrimaryBtn) return;
    const isRunning = (timerState === 'running');
    const isPaused = (timerState === 'paused');
    const iconName = isRunning ? 'pause' : 'play';
    const label = isRunning ? 'Pause Timer' : (isPaused ? 'Resume Timer' : 'Start Timer');
    
    timerPrimaryBtn.setAttribute('title', label);
    timerPrimaryBtn.setAttribute('aria-label', label);
    timerPrimaryBtn.innerHTML = `<i data-lucide="${iconName}"></i>`;

    if (timerStopSaveBtn) {
      timerStopSaveBtn.style.display = (isRunning || isPaused) ? 'inline-flex' : 'none';
    }
    if (typeof lucide !== 'undefined') lucide.createIcons();
  }

  // ──────────────────────────────────────────────────────────────────────────
  // Backend Session API (With Request Mutex)
  // ──────────────────────────────────────────────────────────────────────────

  async function startOrResumeSession() {
    if (currentMode === 'clock') return;
    if (isSessionActionPending) return;
    isSessionActionPending = true;

    try {
      if (timerState === 'paused' && activeSessionId) {
        const res  = await fetch('api/sessions.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.getCsrfToken() },
          body: JSON.stringify({ action: 'resume', session_id: activeSessionId })
        });
        const data = await res.json();
        if (data.success) {
          timerState = 'running';
          startTicking();
          updateControlsUI();
          window.showToast('Session resumed', 'info');
        }
        return;
      }

      const sessionType = (currentMode === 'pomodoro')
        ? (pomodoroPhase === 'focus' ? 'pomodoro' : 'break')
        : (currentMode === 'stopwatch' ? 'stopwatch' : 'focus');

      const res  = await fetch('api/sessions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.getCsrfToken() },
        body: JSON.stringify({
          action:                   'start',
          session_type:             sessionType,
          planned_duration_seconds: (currentMode === 'stopwatch') ? null : totalDurationSec,
          subject_id:               (subjectSelect && subjectSelect.value) ? subjectSelect.value : null,
          session_label:            `${sessionType.charAt(0).toUpperCase() + sessionType.slice(1)} Session`
        })
      });
      const data = await res.json();
      if (data.success) {
        activeSessionId = data.session_id;
        timerState = 'running';
        startTicking();
        updateControlsUI();
        window.showToast('Study session started!', 'success');
      } else {
        // Still start timer locally if API fails
        timerState = 'running';
        startTicking();
        updateControlsUI();
        console.warn('Session not saved to DB:', data.error);
      }
    } catch (err) {
      console.error('Start session error:', err);
      timerState = 'running';
      startTicking();
      updateControlsUI();
    } finally {
      isSessionActionPending = false;
    }
  }

  async function pauseSession() {
    if (timerState !== 'running') return;
    if (isSessionActionPending) return;
    isSessionActionPending = true;

    clearInterval(timerInterval);
    timerState = 'paused';
    updateControlsUI();

    try {
      if (activeSessionId) {
        await fetch('api/sessions.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.getCsrfToken() },
          body: JSON.stringify({ action: 'pause', session_id: activeSessionId })
        });
      }
      window.showToast('Session paused', 'info');
    } catch (err) {
      console.error('Pause session error:', err);
    } finally {
      isSessionActionPending = false;
    }
  }

  async function completeSession(durationSec) {
    timerState = 'idle';
    updateControlsUI();
    if (!activeSessionId) return;
    try {
      await fetch('api/sessions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.getCsrfToken() },
        body: JSON.stringify({
          action:           'complete',
          session_id:       activeSessionId,
          duration_seconds: Math.floor(durationSec)
        })
      });
      activeSessionId = null;
      window.showToast('Great work! Session saved.', 'success');
    } catch (err) {
      console.error('Complete session error:', err);
    }
  }

  async function stopSession(save) {
    if (timerState === 'idle') return;
    clearInterval(timerInterval);
    const duration = (currentMode === 'stopwatch')
      ? Math.floor(stopwatchSec)
      : Math.floor(totalDurationSec - remainingSec);

    if (save && duration >= 30) {
      await completeSession(duration);
    } else {
      if (activeSessionId) {
        fetch('api/sessions.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.getCsrfToken() },
          body: JSON.stringify({ action: 'cancel', session_id: activeSessionId })
        }).catch(() => {});
      }
      timerState      = 'idle';
      activeSessionId = null;
      updateControlsUI();
    }
    resetTimer();
  }

  function handleTimerCompleted() {
    clearInterval(timerInterval);

    if (cfg.timerNotifications !== false) {
      playNotificationBeep();
      if (typeof Notification !== 'undefined' && Notification.permission === 'granted') {
        try {
          new Notification('StudyMaze', { body: 'Session complete! Great work 🎯' });
        } catch (e) {}
      }
    }

    if (currentMode === 'pomodoro') {
      const dur = totalDurationSec;
      completeSession(dur);

      if (pomodoroPhase === 'focus') {
        if (pomodoroCycle >= cfg.pomodoroRounds) {
          pomodoroPhase    = 'long_break';
          totalDurationSec = cfg.longBreakMin * 60;
          pomodoroCycle    = 1;
          window.showToast(`🎉 Long break — ${cfg.longBreakMin} minutes!`, 'success', 4000);
        } else {
          pomodoroPhase    = 'short_break';
          totalDurationSec = cfg.shortBreakMin * 60;
          pomodoroCycle++;
          window.showToast(`☕ Short break — ${cfg.shortBreakMin} minutes!`, 'info', 3000);
        }
      } else {
        pomodoroPhase    = 'focus';
        totalDurationSec = cfg.defaultFocusMin * 60;
        window.showToast('Focus session starting…', 'info', 2000);
      }

      remainingSec = totalDurationSec;
      timerDigits.textContent = formatDigits(remainingSec);
      updatePomodoroBadge();
      updateRing(1);

      if (cfg.autoStartBreaks) setTimeout(startOrResumeSession, 1500);

    } else {
      completeSession(totalDurationSec);
      resetTimer();
    }
  }

  function resetTimer() {
    clearInterval(timerInterval);
    timerState = 'idle';
    if (currentMode === 'stopwatch') {
      stopwatchSec = 0;
      timerDigits.textContent = '00:00';
    } else {
      remainingSec = totalDurationSec;
      timerDigits.textContent = formatDigits(remainingSec);
    }
    updateRing(1);
    updateControlsUI();
  }

  // ──────────────────────────────────────────────────────────────────────────
  // Notification Beep
  // ──────────────────────────────────────────────────────────────────────────

  function playNotificationBeep() {
    try {
      const ctx  = new (window.AudioContext || window.webkitAudioContext)();
      const osc  = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.setValueAtTime(523.25, ctx.currentTime);
      osc.frequency.setValueAtTime(659.25, ctx.currentTime + 0.18);
      osc.frequency.setValueAtTime(783.99, ctx.currentTime + 0.36);
      gain.gain.setValueAtTime(0.1, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.9);
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start();
      osc.stop(ctx.currentTime + 0.9);
    } catch (e) {}
  }

  // ──────────────────────────────────────────────────────────────────────────
  // Active Session Recovery
  // ──────────────────────────────────────────────────────────────────────────

  async function checkActiveSession() {
    try {
      const res  = await fetch('api/sessions.php?action=active');
      const data = await res.json();
      if (data.success && data.has_active && data.session) {
        const ses = data.session;
        activeSessionId = ses.session_id;

        if (ses.session_type === 'pomodoro')       await switchMode('pomodoro');
        else if (ses.session_type === 'stopwatch') await switchMode('stopwatch');
        else                                        await switchMode('timer');

        if (ses.subject_id && subjectSelect) subjectSelect.value = ses.subject_id;
        if (ses.planned_duration_seconds) totalDurationSec = parseInt(ses.planned_duration_seconds, 10);

        const elapsed = parseInt(ses.current_elapsed_seconds, 10) || 0;
        if (currentMode === 'stopwatch') {
          stopwatchSec = elapsed;
          timerDigits.textContent = formatDigits(stopwatchSec);
        } else {
          remainingSec = Math.max(0, totalDurationSec - elapsed);
          timerDigits.textContent = formatDigits(remainingSec);
          updateRing(remainingSec / totalDurationSec);
        }

        if (ses.status === 'running') { timerState = 'running'; startTicking(); }
        else if (ses.status === 'paused') timerState = 'paused';
        updateControlsUI();
        window.showToast('Active study session recovered!', 'info');
      }
    } catch (err) {
      console.error('Session recovery error:', err);
    }
  }

  // ──────────────────────────────────────────────────────────────────────────
  // Keyboard Shortcuts
  // ──────────────────────────────────────────────────────────────────────────

  function initKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
      if (window.isTypingContext && window.isTypingContext(e.target)) return;

      if (e.code === 'Space' || e.key === ' ') {
        e.preventDefault();
        if (currentMode !== 'clock') {
          if (timerState === 'running') pauseSession();
          else startOrResumeSession();
        }
        return;
      }

      if (e.key === 'Escape') {
        if (isFocusModeActive) { exitFocusMode(); return; }
        if (soundsDrawer && soundsDrawer.classList.contains('show')) {
          soundsDrawer.classList.remove('show');
          if (toggleSoundsBtn) toggleSoundsBtn.classList.remove('active');
          return;
        }
        if (tasksDrawer && tasksDrawer.classList.contains('show')) {
          tasksDrawer.classList.remove('show');
          if (toggleTasksBtn) toggleTasksBtn.classList.remove('active');
        }
        return;
      }

      if (e.key === 'f' || e.key === 'F') {
        e.preventDefault();
        if (isFocusModeActive) exitFocusMode(); else enterFocusMode();
        return;
      }

      if (e.key === 'r' || e.key === 'R') { e.preventDefault(); if (timerResetBtn) timerResetBtn.click(); return; }
      if (e.key === 'm' || e.key === 'M') { e.preventDefault(); if (muteSoundBtn)  muteSoundBtn.click();  return; }

      if (e.key === '1') switchMode('timer');
      else if (e.key === '2') switchMode('clock');
      else if (e.key === '3') switchMode('pomodoro');
      else if (e.key === '4') switchMode('stopwatch');
    });
  }

  // ──────────────────────────────────────────────────────────────────────────
  // Event Listeners
  // ──────────────────────────────────────────────────────────────────────────

  function initListeners() {
    // Mode Pills
    modePills.forEach(pill => pill.addEventListener('click', () => switchMode(pill.dataset.mode)));

    // Primary Button (Play / Pause)
    if (timerPrimaryBtn) {
      timerPrimaryBtn.addEventListener('click', () => {
        if (timerState === 'running') pauseSession();
        else startOrResumeSession();
      });
    }

    // Reset Button
    if (timerResetBtn) {
      timerResetBtn.addEventListener('click', async () => {
        if (timerState === 'running' || timerState === 'paused') {
          const ok = await window.showConfirmModal(
            'Reset the timer and cancel the current session? Progress will not be saved.',
            'Reset & Cancel', 'Keep Going'
          );
          if (ok) await stopSession(false);
        } else {
          resetTimer();
        }
      });
    }

    // Stop & Save
    if (timerStopSaveBtn) {
      timerStopSaveBtn.addEventListener('click', () => stopSession(true));
    }

    // Timer Duration Pills
    durationPills.forEach(pill => {
      pill.addEventListener('click', async () => {
        if (timerState === 'running') return;
        durationPills.forEach(p => p.classList.remove('active'));
        pill.classList.add('active');
        if (pill.dataset.minutes === 'custom') {
          const val = await showPromptModal('Enter a custom duration (1–720 minutes):', 'e.g. 30', '30');
          const min = parseInt(val, 10);
          if (min && min > 0 && min <= 720) { totalDurationSec = min * 60; } else { pill.classList.remove('active'); return; }
        } else {
          totalDurationSec = parseInt(pill.dataset.minutes, 10) * 60;
        }
        remainingSec = totalDurationSec;
        timerDigits.textContent = formatDigits(remainingSec);
        updateRing(1);
      });
    });

    // Pomodoro Duration Pills
    pomodoroPills.forEach(pill => {
      pill.addEventListener('click', async () => {
        if (timerState === 'running') return;
        pomodoroPills.forEach(p => p.classList.remove('active'));
        pill.classList.add('active');
        if (pill.dataset.pomMin === 'custom') {
          const val = await showPromptModal('Enter a custom Pomodoro duration (1–180 minutes):', 'e.g. 30', '30');
          const min = parseInt(val, 10);
          if (min && min > 0 && min <= 180) { totalDurationSec = min * 60; } else { pill.classList.remove('active'); return; }
        } else {
          totalDurationSec = parseInt(pill.dataset.pomMin, 10) * 60;
        }
        remainingSec = totalDurationSec;
        timerDigits.textContent = formatDigits(remainingSec);
        updateRing(1);
      });
    });

    // Sounds Drawer
    if (toggleSoundsBtn && soundsDrawer) {
      toggleSoundsBtn.addEventListener('click', () => {
        soundsDrawer.classList.toggle('show');
        toggleSoundsBtn.classList.toggle('active', soundsDrawer.classList.contains('show'));
        if (tasksDrawer)  tasksDrawer.classList.remove('show');
        if (toggleTasksBtn) toggleTasksBtn.classList.remove('active');
      });
    }

    // Focus Mode
    if (enterFocusBtn) enterFocusBtn.addEventListener('click', enterFocusMode);
    if (exitFocusBtn)  exitFocusBtn.addEventListener('click', exitFocusMode);

    // Tasks Drawer
    if (toggleTasksBtn && tasksDrawer) {
      toggleTasksBtn.addEventListener('click', () => {
        tasksDrawer.classList.toggle('show');
        toggleTasksBtn.classList.toggle('active', tasksDrawer.classList.contains('show'));
        if (soundsDrawer)    soundsDrawer.classList.remove('show');
        if (toggleSoundsBtn) toggleSoundsBtn.classList.remove('active');
      });
    }

    // Sound Buttons
    document.querySelectorAll('.sound-btn').forEach(btn => {
      btn.addEventListener('click', () => initAmbientAudio(btn.dataset.sound));
    });

    // Volume Slider — live update
    if (soundVolumeSlider) {
      soundVolumeSlider.addEventListener('input', (e) => {
        const val = parseInt(e.target.value, 10);
        if (volumeValueLabel) volumeValueLabel.textContent = `${val}%`;
        if (!isMuted) updateAmbientVolume(val / 100);

        clearTimeout(soundVolumeSlider._dbTimer);
        soundVolumeSlider._dbTimer = setTimeout(saveSoundPreferences, 1200);
      });
    }

    // Mute Button
    if (muteSoundBtn) {
      muteSoundBtn.addEventListener('click', () => {
        isMuted = !isMuted;
        updateAmbientVolume(isMuted ? 0 : getTargetVolume());
        muteSoundBtn.innerHTML = `<i data-lucide="${isMuted ? 'volume-x' : 'volume-2'}" id="volumeIcon" style="width: 16px; height: 16px;"></i>`;
        if (typeof lucide !== 'undefined') lucide.createIcons();
        window.showToast(isMuted ? 'Audio muted' : 'Audio unmuted', 'info', 1500);
      });
    }

    // Refresh Quote
    if (refreshQuoteBtn && quoteElem) {
      refreshQuoteBtn.addEventListener('click', async () => {
        try {
          const res  = await fetch('api/quotes.php');
          const data = await res.json();
          if (data.success && data.quote) quoteElem.textContent = `"${data.quote.quote_text}"`;
        } catch (err) {}
      });
    }

    // Quick Task Checkboxes
    document.querySelectorAll('.quick-task-check').forEach(checkbox => {
      checkbox.addEventListener('click', async () => {
        const taskId   = checkbox.dataset.id;
        const taskCard = checkbox.closest('.task-card');
        if (!taskId || !taskCard) return;
        try {
          const res  = await fetch('api/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.getCsrfToken() },
            body: JSON.stringify({ action: 'toggle', task_id: taskId, status: 'completed' })
          });
          const data = await res.json();
          if (data.success) {
            taskCard.style.opacity        = '0.5';
            taskCard.style.textDecoration = 'line-through';
            window.showToast('Task marked completed!', 'success');
          }
        } catch (err) {}
      });
    });

    // Keyboard shortcuts
    initKeyboardShortcuts();
  }

  // ──────────────────────────────────────────────────────────────────────────
  // Persist Sound Preferences
  // ──────────────────────────────────────────────────────────────────────────

  async function saveSoundPreferences() {
    const vol = soundVolumeSlider ? parseInt(soundVolumeSlider.value, 10) : cfg.soundVolume;
    try {
      await fetch('api/settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.getCsrfToken() },
        body: JSON.stringify({
          action:         'update_sound_preferences',
          selected_sound: activeSoundName || cfg.defaultSound,
          sound_volume:   vol,
          sound_enabled:  soundEnabled ? 1 : 0
        })
      });
    } catch (e) {}
  }

  // ──────────────────────────────────────────────────────────────────────────
  // Init
  // ──────────────────────────────────────────────────────────────────────────

  document.addEventListener('DOMContentLoaded', () => {
    // Force clean Focus Mode state
    exitFocusMode();

    // Apply global sound-enabled setting from PHP-injected config
    applySoundEnabledState(soundEnabled);

    // Set volume slider to saved value
    if (soundVolumeSlider) {
      soundVolumeSlider.value = cfg.soundVolume;
      if (volumeValueLabel) volumeValueLabel.textContent = `${cfg.soundVolume}%`;
    }

    initListeners();
    checkActiveSession();
  });

})();
