/**
 * AudioPlaybackManager
 * مدير تشغيل الصوت الحصري (Singleton Coordinator)
 * يضمن تشغيل رسالة صوتية واحدة فقط في كامل التطبيق في نفس الوقت،
 * وإيقاف أي صوت شغال فوراً وبشكل لحظي متزامن عند بدء تشغيل رسالة أخرى أو بدء تسجيل صوت جديد.
 */

type PlaybackListener = (activeId: string | null) => void;

class AudioPlaybackManager {
  private activeId: string | null = null;
  private activePlayer: any = null;
  private pauseActiveCallback: (() => void) | null = null;
  private listeners: Set<PlaybackListener> = new Set();

  /**
   * تسجيل مشغل نشط جديد وإيقاف أي مشغل سابق فوراً وبشكل متزامن
   */
  claimPlayback(id: string, player: any, pauseCallback: () => void) {
    if (this.activeId && this.activeId !== id) {
      if (this.activePlayer) {
        try {
          this.activePlayer.pause();
        } catch (err) {
          console.warn('[AudioPlaybackManager] Error pausing activePlayer:', err);
        }
      }
      if (this.pauseActiveCallback) {
        try {
          this.pauseActiveCallback();
        } catch (err) {
          console.warn('[AudioPlaybackManager] Error in pauseActiveCallback:', err);
        }
      }
    }
    this.activeId = id;
    this.activePlayer = player;
    this.pauseActiveCallback = pauseCallback;
    this.notify();
  }

  /**
   * إشعار بتوقف المشغل الحالي
   */
  releasePlayback(id: string) {
    if (this.activeId === id) {
      this.activeId = null;
      this.activePlayer = null;
      this.pauseActiveCallback = null;
      this.notify();
    }
  }

  /**
   * إيقاف أي صوت يعمل حالياً (مثلاً عند بدء التسجيل أو حذف رسالة)
   */
  stopAll() {
    if (this.activePlayer) {
      try {
        this.activePlayer.pause();
      } catch {}
    }
    if (this.pauseActiveCallback) {
      try {
        this.pauseActiveCallback();
      } catch {}
    }
    this.activeId = null;
    this.activePlayer = null;
    this.pauseActiveCallback = null;
    this.notify();
  }

  getActiveId(): string | null {
    return this.activeId;
  }

  subscribe(listener: PlaybackListener): () => void {
    this.listeners.add(listener);
    return () => {
      this.listeners.delete(listener);
    };
  }

  private notify() {
    for (const listener of this.listeners) {
      try {
        listener(this.activeId);
      } catch {}
    }
  }
}

export const audioPlaybackManager = new AudioPlaybackManager();
