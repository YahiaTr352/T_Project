import { useEffect, useState } from 'react';
import { Platform } from 'react-native';

// In-memory cache to prevent re-generating thumbnails for the same video URL repeatedly
const thumbnailCache = new Map<string, string>();

let VideoThumbnailsModule: typeof import('expo-video-thumbnails') | null = null;
try {
  // eslint-disable-next-line @typescript-eslint/no-require-imports
  VideoThumbnailsModule = require('expo-video-thumbnails');
} catch (e) {
  console.warn('[videoThumbnails] expo-video-thumbnails module could not be loaded:', e);
}

/**
 * Retrieves or generates a thumbnail for a given video URL (local or remote).
 * Utilizes in-memory caching to optimize performance and prevent duplicate network/decoding work.
 */
export async function getVideoThumbnail(
  videoUrl?: string | null,
  timeMs = 500
): Promise<string | null> {
  if (!videoUrl || typeof videoUrl !== 'string' || !videoUrl.trim()) {
    return null;
  }

  const trimmed = videoUrl.trim();

  // Return cached thumbnail immediately if available
  if (thumbnailCache.has(trimmed)) {
    return thumbnailCache.get(trimmed)!;
  }

  if (!VideoThumbnailsModule?.getThumbnailAsync) {
    return null;
  }

  try {
    const result = await VideoThumbnailsModule.getThumbnailAsync(trimmed, {
      time: timeMs,
      quality: 0.75,
    });

    if (result?.uri) {
      thumbnailCache.set(trimmed, result.uri);
      return result.uri;
    }
  } catch (err) {
    // If thumbnail extraction fails (e.g. invalid codec or remote asset 404), fail silently
    // and let the caller fall back to poster or native decoder.
  }

  return null;
}

/**
 * Manually register a known thumbnail for a video URL into the cache.
 */
export function setCachedVideoThumbnail(videoUrl: string, thumbnailUri: string) {
  if (videoUrl && thumbnailUri) {
    thumbnailCache.set(videoUrl.trim(), thumbnailUri.trim());
  }
}

/**
 * React hook to smoothly resolve a video thumbnail across platforms.
 * 
 * Logic:
 * 1. If server provides a valid thumbnail_url in metadata, use it directly.
 * 2. On Android, Glide natively decodes video URLs directly into frames, so videoUrl can be used as fallback.
 * 3. On iOS, SDWebImage cannot decode video URLs into image frames. Therefore, we use expo-video-thumbnails
 *    to extract the frame and supply a genuine image URI to expo-image.
 */
export function useVideoThumbnail(
  videoUrl?: string | null,
  serverThumbnail?: string | null
): {
  thumbnailUri: string | null;
  loading: boolean;
  failed: boolean;
} {
  const effectiveServerThumb = serverThumbnail?.trim() || null;
  const cleanVideoUrl = videoUrl?.trim() || null;

  const [thumbnailUri, setThumbnailUri] = useState<string | null>(() => {
    if (effectiveServerThumb) return effectiveServerThumb;
    if (cleanVideoUrl && thumbnailCache.has(cleanVideoUrl)) {
      return thumbnailCache.get(cleanVideoUrl)!;
    }
    // On Android, Glide can render videoUrl directly as thumbnail
    if (Platform.OS === 'android' && cleanVideoUrl) {
      return cleanVideoUrl;
    }
    return null;
  });

  const [loading, setLoading] = useState<boolean>(() => {
    if (effectiveServerThumb) return false;
    if (cleanVideoUrl && thumbnailCache.has(cleanVideoUrl)) return false;
    // On iOS, we need to generate it if we don't have a thumbnail yet
    return Platform.OS === 'ios' && Boolean(cleanVideoUrl);
  });

  const [failed, setFailed] = useState(false);

  useEffect(() => {
    let isMounted = true;

    if (effectiveServerThumb) {
      setThumbnailUri(effectiveServerThumb);
      setLoading(false);
      setFailed(false);
      return;
    }

    if (!cleanVideoUrl) {
      setThumbnailUri(null);
      setLoading(false);
      setFailed(false);
      return;
    }

    if (thumbnailCache.has(cleanVideoUrl)) {
      setThumbnailUri(thumbnailCache.get(cleanVideoUrl)!);
      setLoading(false);
      setFailed(false);
      return;
    }

    // On iOS or when needed, generate thumbnail asynchronously
    setLoading(true);
    setFailed(false);

    getVideoThumbnail(cleanVideoUrl)
      .then((generatedUri) => {
        if (!isMounted) return;
        if (generatedUri) {
          setThumbnailUri(generatedUri);
          setFailed(false);
        } else {
          // On Android, fallback to cleanVideoUrl (Glide)
          if (Platform.OS === 'android') {
            setThumbnailUri(cleanVideoUrl);
            setFailed(false);
          } else {
            setFailed(true);
          }
        }
      })
      .catch(() => {
        if (!isMounted) return;
        if (Platform.OS === 'android') {
          setThumbnailUri(cleanVideoUrl);
        } else {
          setFailed(true);
        }
      })
      .finally(() => {
        if (isMounted) setLoading(false);
      });

    return () => {
      isMounted = false;
    };
  }, [cleanVideoUrl, effectiveServerThumb]);

  return { thumbnailUri, loading, failed };
}
