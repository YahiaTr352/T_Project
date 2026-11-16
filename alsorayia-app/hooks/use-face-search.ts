import { searchPersonByImage } from '@/lib/api/familyTreeApi';
import { imageUriToBase64 } from '@/lib/api/authApi';
import * as Haptics from 'expo-haptics';
import * as ImagePicker from 'expo-image-picker';
import { useCallback, useState } from 'react';
import { CustomAlert } from '@/components/ui/AlertModal';
import { Platform } from 'react-native';



export interface FaceSearchPerson {
  id: number;
  first_name?: string;
  last_name?: string;
  full_name: string;
  avatar?: string;
  photo_url?: string;
  gender?: string;
  birth_date?: string;
  occupation?: string;
}

export interface FaceSearchMatch {
  person: FaceSearchPerson;
  similarity: string; // e.g. "95.2%"
  similarity_score?: number;
}

export function useFaceSearch() {
  const [isScanning, setIsScanning] = useState(false);
  const [selectedImageUri, setSelectedImageUri] = useState<string | null>(null);
  const [matches, setMatches] = useState<FaceSearchMatch[]>([]);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const resetSearch = useCallback(() => {
    setIsScanning(false);
    setSelectedImageUri(null);
    setMatches([]);
    setErrorMessage(null);
  }, []);

  const performSearch = useCallback(async (asset: ImagePicker.ImagePickerAsset): Promise<FaceSearchMatch[]> => {
    setIsScanning(true);
    setErrorMessage(null);
    setMatches([]);
    setSelectedImageUri(asset.uri);

    try {
      let base64 = asset.base64;
      if (!base64) {
        try {
          base64 = await imageUriToBase64(asset.uri);
        } catch {}
      }

      let res: any = null;

      if (base64) {
        const dataUri = base64.startsWith('data:') ? base64 : `data:image/jpeg;base64,${base64}`;
        res = await searchPersonByImage({ image_base64: dataUri });
      }

      if (!res || (res?.errors?.image && !res?.data)) {
        const localUri = asset.uri;
        const cleanUri = Platform.OS === 'android' ? localUri : localUri.replace('file://', '');
        const rawExt = localUri.split('.').pop()?.split('?')[0]?.toLowerCase() || 'jpg';
        const ext = ['jpg', 'jpeg', 'png', 'webp'].includes(rawExt) ? (rawExt === 'jpeg' ? 'jpg' : rawExt) : 'jpg';
        const cleanFilename = `face_search_${Date.now()}.${ext}`;
        const mimeType = ext === 'png' ? 'image/png' : ext === 'webp' ? 'image/webp' : 'image/jpeg';

        const formData = new FormData();
        formData.append('image', {
          uri: cleanUri,
          name: cleanFilename,
          type: mimeType,
        } as any);

        res = await searchPersonByImage(formData);
      }

      if (res && res.status === 'success' && Array.isArray(res.data)) {
        const normalizedMatches: FaceSearchMatch[] = res.data.map((item: any) => ({
          person: {
            ...item.person,
            photo_url: item.person.photo_url || item.person.avatar,
          },
          similarity: item.similarity,
          similarity_score:
            typeof item.similarity_score === 'number'
              ? item.similarity_score
              : parseFloat(item.similarity) || 0,
        }));

        setMatches(normalizedMatches);

        if (normalizedMatches.length > 0) {
          Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
        } else {
          Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning);
          setErrorMessage('لم يتم العثور على أي تطابق للوجه في شجرة العائلة.');
        }
        return normalizedMatches;
      } else {
        setMatches([]);
        Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning);
        setErrorMessage(res?.message || 'لم يتم العثور على أي تطابق للوجه في شجرة العائلة.');
        return [];
      }
    } catch (err: any) {
      console.error('useFaceSearch error:', err);
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      const msg = 'فشل الاتصال بخدمة التعرف على الوجوه، يرجى المحاولة لاحقاً.';
      setErrorMessage(msg);
      CustomAlert.alert('تعذر البحث', msg);
      return [];
    } finally {
      setIsScanning(false);
    }
  }, []);

  const takePhoto = useCallback(async (): Promise<FaceSearchMatch[] | null> => {
    const permission = await ImagePicker.requestCameraPermissionsAsync();
    if (!permission.granted) {
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      CustomAlert.alert('صلاحية مطلوبة', 'يرجى السماح بالوصول إلى الكاميرا لالتقاط صورة للبحث.');
      return null;
    }

    const result = await ImagePicker.launchCameraAsync({
      mediaTypes: ['images'],
      allowsEditing: true,
      quality: 0.7,
      base64: true,
    });

    if (!result.canceled && result.assets && result.assets.length > 0) {
      return performSearch(result.assets[0]);
    }
    return null;
  }, [performSearch]);

  const pickImage = useCallback(async (): Promise<FaceSearchMatch[] | null> => {
    const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!permission.granted) {
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      CustomAlert.alert('صلاحية مطلوبة', 'يرجى السماح بالوصول إلى معرض الصور لاختيار صورة للبحث.');
      return null;
    }


    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images'],
      allowsEditing: true,
      quality: 0.7,
      base64: true,
    });

    if (!result.canceled && result.assets && result.assets.length > 0) {
      return performSearch(result.assets[0]);
    }
    return null;
  }, [performSearch]);

  return {
    isScanning,
    selectedImageUri,
    matches,
    errorMessage,
    takePhoto,
    pickImage,
    resetSearch,
  };
}
