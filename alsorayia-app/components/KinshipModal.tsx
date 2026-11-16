import { Colors } from '@/constants/theme';
import { useAuth } from '@/contexts/auth-context';
import { FaceSearchMatch, useFaceSearch } from '@/hooks/use-face-search';
import { fetchKinship, fetchPersonsSearchList } from '@/lib/api/familyTreeApi';
import { searchAndRankPersons } from '@/lib/utils/searchEngine';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { useRouter } from 'expo-router';
import * as Sharing from 'expo-sharing';
import React, { useCallback, useEffect, useRef, useState } from 'react';
import { CustomAlert } from '@/components/ui/AlertModal';
import {
  ActivityIndicator,
  Keyboard,
  KeyboardAvoidingView,
  Modal,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  useWindowDimensions,
  View,
} from 'react-native';

import Svg, { Defs, Path, Stop, LinearGradient as SvgLinearGradient } from 'react-native-svg';
import { captureRef } from 'react-native-view-shot';

interface KinshipModalProps {
  visible: boolean;
  onClose: () => void;
  targetPerson: { id: number; full_name?: string; first_name?: string; photo_url?: string } | null;
  mode?: 'compare_with_me' | 'custom_compare';
  colors: (typeof Colors)['light'] | (typeof Colors)['dark'] | any;
}

export const KinshipModal: React.FC<KinshipModalProps> = ({
  visible,
  onClose,
  targetPerson,
  mode = 'compare_with_me',
  colors,
}) => {
  const router = useRouter();
  const { user } = useAuth();
  const userPersonId = user?.id;
  const { width: windowWidth } = useWindowDimensions();
  const [measuredCardWidth, setMeasuredCardWidth] = useState(0);

  const [loading, setLoading] = useState(false);
  const [sharingImg, setSharingImg] = useState(false);
  const [kinshipData, setKinshipData] = useState<any>(null);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);

  const treeCardRef = useRef<View>(null);

  // Custom Search State
  const [isSearchActive, setIsSearchActive] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [allPersons, setAllPersons] = useState<any[]>([]);
  const [searchResults, setSearchResults] = useState<any[]>([]);
  const [loadingPersons, setLoadingPersons] = useState(false);

  // Face Recognition Search State & Hook
  const {
    isScanning: isFaceScanning,
    takePhoto,
    pickImage,
    errorMessage: faceErrorMessage,
  } = useFaceSearch();
  const [isFacePickerVisible, setIsFacePickerVisible] = useState(false);
  const [isMultipleMatchesVisible, setIsMultipleMatchesVisible] = useState(false);
  const [candidateMatches, setCandidateMatches] = useState<FaceSearchMatch[]>([]);

  const loadKinshipData = useCallback((personAId?: number, personBId?: number) => {
    if (!personBId) return;
    setLoading(true);
    setErrorMsg(null);
    setKinshipData(null);

    fetchKinship(personBId, personAId)
      .then((res) => {
        setLoading(false);
        if (res?.success && res?.data?.relationship) {
          setKinshipData(res.data);
        } else if (res?.data?.requires_person_a_selection) {
          setIsSearchActive(true);
          setErrorMsg('لم يتم التعرف على شخصيتك تلقائياً؛ يرجى اختيار الشخص الأول من قائمة البحث.');
        } else {
          setErrorMsg(res?.message || 'تعذر حساب صلة القرابة حالياً.');
        }
      })
      .catch(() => {
        setLoading(false);
        setIsSearchActive(true);
        setErrorMsg('حدث خطأ أثناء طلب احتساب صلة القرابة.');
      });
  }, []);

  useEffect(() => {
    if (visible && targetPerson?.id) {
      setIsSearchActive(mode === 'custom_compare');
      setSearchQuery('');
      setSearchResults([]);

      if (mode === 'compare_with_me') {
        loadKinshipData(userPersonId ? Number(userPersonId) : undefined, targetPerson.id);
      }
    }
  }, [visible, targetPerson, mode, loadKinshipData, userPersonId]);

  // Load all persons for search list
  useEffect(() => {
    let active = true;
    if (visible && isSearchActive && allPersons.length === 0) {
      queueMicrotask(() => {
        if (active) setLoadingPersons(true);
      });
      fetchPersonsSearchList()
        .then((res) => {
          if (!active) return;
          const list = res?.persons || res?.data?.persons || res?.data || (Array.isArray(res) ? res : []);
          if (Array.isArray(list)) {
            setAllPersons(list);
          }
        })
        .catch(() => { })
        .finally(() => {
          if (active) setLoadingPersons(false);
        });
    }
    return () => {
      active = false;
    };
  }, [visible, isSearchActive, allPersons.length]);

  const handleSearchChange = (text: string) => {
    setSearchQuery(text);
    if (!text.trim()) {
      setSearchResults([]);
      return;
    }
    const matches = searchAndRankPersons(allPersons, text, 25);
    setSearchResults(matches);
  };

  const handleSelectPerson = useCallback(
    (chosen: any) => {
      Keyboard.dismiss();
      setIsSearchActive(false);
      setIsMultipleMatchesVisible(false);
      setSearchQuery('');
      setSearchResults([]);

      if (targetPerson?.id) {
        if (mode === 'compare_with_me') {
          loadKinshipData(chosen.id, targetPerson.id);
        } else {
          loadKinshipData(targetPerson.id, chosen.id);
        }
      }
    },
    [mode, targetPerson, loadKinshipData]
  );

  const handleFaceResult = useCallback(
    (matches: FaceSearchMatch[] | null) => {
      if (!matches || matches.length === 0) return;
      if (matches.length === 1) {
        handleSelectPerson(matches[0].person);
      } else {
        setCandidateMatches(matches);
        setIsMultipleMatchesVisible(true);
      }
    },
    [handleSelectPerson]
  );

  const onTakePhoto = async () => {
    setIsFacePickerVisible(false);
    const matches = await takePhoto();
    handleFaceResult(matches);
  };

  const onPickImage = async () => {
    setIsFacePickerVisible(false);
    const matches = await pickImage();
    handleFaceResult(matches);
  };

  const handleShareImage = async () => {
    if (!treeCardRef.current || sharingImg) return;

    setSharingImg(true);
    try {
      const uri = await captureRef(treeCardRef, {
        format: 'png',
        quality: 1.0,
        result: 'tmpfile',
      });

      const isAvailable = await Sharing.isAvailableAsync();
      if (isAvailable) {
        await Sharing.shareAsync(uri, {
          mimeType: 'image/png',
          dialogTitle: 'صلة القرابة والنسب - عائلة السريّع 🌴',
          UTI: 'public.png',
        });
      } else {
        CustomAlert.alert('تنبيه', 'المشاركة غير متاحة على هذا الجهاز.');
      }
    } catch {
      CustomAlert.alert('خطأ', 'تعذر التقاط ومشاركة صورة صلة القرابة.');
    } finally {
      setSharingImg(false);
    }
  };

  if (!visible) return null;

  const relationship = kinshipData?.relationship;
  const ancestor = relationship?.common_ancestor;
  const branchA = kinshipData?.branch_a || [];
  const branchB = kinshipData?.branch_b || [];
  const upperChain = kinshipData?.upper_chain || [];
  const isDirect = !!relationship?.is_direct || (!ancestor && relationship?.type === 'direct');
  const isVirtualRoot = relationship?.type === 'family_root' || (ancestor && (ancestor.id === 0 || ancestor.is_virtual_family_root));
  const isAffinalSpouse = relationship?.type === 'affinal_spouse';
  const isUnrelated = relationship?.type === 'unrelated';

  const familyPerson = relationship?.family_person;
  const spousePerson = relationship?.spouse_info;

  const cleanFamilyChain = (branchA.length >= branchB.length ? branchA : branchB)
    .filter((p: any) => p.id !== 0 && !p.is_virtual_family_root)
    .reverse();

  const familyTargetNode = familyPerson || (cleanFamilyChain.length > 0 ? cleanFamilyChain[cleanFamilyChain.length - 1] : targetPerson);
  const spouseTargetNode = spousePerson || targetPerson;

  // Filter out the apex ancestor from lower columns
  const cleanBranchA = ancestor
    ? branchA.filter((p: any) => p.id !== ancestor.id && !p.is_virtual_family_root).reverse()
    : branchA.slice().reverse();

  const cleanBranchB = ancestor
    ? branchB.filter((p: any) => p.id !== ancestor.id && !p.is_virtual_family_root).reverse()
    : branchB.slice().reverse();

  const getShortName = (p: any) => {
    if (!p) return '';
    if (
      p.id === 0 ||
      p.is_virtual_family_root ||
      p.name === 'عائلة السريّع' ||
      p.full_name === 'عائلة السريّع' ||
      p.name === 'عائلة السريّع' ||
      p.full_name === 'عائلة السريّع' ||
      p.name === 'عائلة السريع' ||
      p.full_name === 'عائلة السريع' ||
      p.name === 'السريّع' ||
      p.full_name === 'السريّع' ||
      p.name === 'السريّع' ||
      p.full_name === 'السريّع' ||
      p.name === 'السريع' ||
      p.full_name === 'السريع'
    ) {
      return 'السريّع';
    }
    const raw = (p.first_name || p.name || p.full_name || '').trim();
    const parts = raw.split(/\s+/);
    if (
      parts.length >= 2 &&
      (parts[0] === 'عبد' ||
        parts[0] === 'ابو' ||
        parts[0] === 'أبو' ||
        parts[0] === 'ام' ||
        parts[0] === 'أم' ||
        parts[0] === 'ابن')
    ) {
      return `${parts[0]} ${parts[1]}`;
    }
    return parts[0] || raw;
  };

  // Available card content width (modal padding: 16*2=32, card padding: 12*2=24 => windowWidth - 56)
  const cardWidth = measuredCardWidth > 0 ? measuredCardWidth : Math.max(300, windowWidth - 56);
  const centerX = cardWidth / 2;

  // Column dimensions tailored for clear avatars: 68px width, 36px gap => centers distance is 104px
  const colWidth = 68;
  const colGap = 36;
  const halfSpan = (colWidth + colGap) / 2;
  const leftX = Math.round(centerX - halfSpan);
  const rightX = Math.round(centerX + halfSpan);

  const renderAvatarNode = (
    person: any,
    type: 'root' | 'apex' | 'target' | 'normal' | 'spouse' = 'normal'
  ) => {
    if (!person) return null;
    const isRoot = type === 'root';
    const isApex = type === 'apex';
    const isTarget = type === 'target';
    const isSpouse = type === 'spouse';

    // Refined enlarged sizing for crystal clear avatars: Apex (62), Root (58), Target (52), Intermediate (48), Spouse (42)
    const size = isApex ? 62 : isRoot ? 58 : isTarget ? 52 : isSpouse ? 42 : 48;
    const radius = size / 2;
    const isFemale = person.gender === 'female';
    const isVirtualRootNode =
      person.id === 0 ||
      person.is_virtual_family_root ||
      person.name === 'عائلة السريّع' ||
      person.full_name === 'عائلة السريّع' ||
      person.name === 'عائلة السريّع' ||
      person.full_name === 'عائلة السريّع' ||
      person.name === 'عائلة السريع' ||
      person.full_name === 'عائلة السريع' ||
      person.name === 'السريّع' ||
      person.full_name === 'السريّع' ||
      person.name === 'السريّع' ||
      person.full_name === 'السريّع' ||
      person.name === 'السريع' ||
      person.full_name === 'السريع';
    const uri = person.photo_url;
    const imageSource = isVirtualRootNode ? require('@/assets/images/family-logo.png') : (uri ? { uri } : null);
    const name = getShortName(person);

    const handleNodePress = () => {
      if (person?.id && Number(person.id) > 0) {
        onClose();
        // Give native modal a smooth exit frame before navigating to avoid race conditions
        setTimeout(() => {
          router.push(`/(app)/person/${person.id}` as any);
        }, 120);
      } else if (
        person?.is_virtual_family_root ||
        person?.id === 0 ||
        person?.name === 'عائلة السريّع' ||
        person?.full_name === 'عائلة السريّع' ||
        person?.name === 'عائلة السريّع' ||
        person?.full_name === 'عائلة السريّع' ||
        person?.name === 'عائلة السريع' ||
        person?.full_name === 'عائلة السريع' ||
        person?.name === 'السريّع' ||
        person?.full_name === 'السريّع' ||
        person?.name === 'السريّع' ||
        person?.full_name === 'السريّع' ||
        person?.name === 'السريع' ||
        person?.full_name === 'السريع'
      ) {
        onClose();
        setTimeout(() => {
          router.push('/(tabs)/sila' as any);
        }, 120);
      }
    };

    return (
      <TouchableOpacity
        style={styles.nodeWrapper}
        onPress={handleNodePress}
        activeOpacity={0.65}
        hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
      >
        {isApex && (
          <View style={styles.crownBadge}>
            <Text style={styles.crownIcon}>👑</Text>
          </View>
        )}

        <View
          style={[
            styles.avatarBorder,
            {
              width: size + (isSpouse ? 3 : 5),
              height: size + (isSpouse ? 3 : 5),
              borderRadius: (size + (isSpouse ? 3 : 5)) / 2,
              borderColor: isRoot || isApex ? '#fbbf24' : isTarget ? '#34d399' : isSpouse ? '#fbbf24' : '#f59e0b',
              backgroundColor: '#09241f',
            },
            (isApex || isRoot) && styles.apexBorderGlow,
            isTarget && styles.targetBorderGlow,
            isSpouse && styles.spouseBorderGlow,
          ]}
        >
          {imageSource ? (
            <Image
              source={imageSource}
              style={{ width: size, height: size, borderRadius: radius }}
              contentFit="cover"
              transition={150}
            />
          ) : (
            <View
              style={[
                styles.avatarPlaceholder,
                {
                  width: size,
                  height: size,
                  borderRadius: radius,
                  backgroundColor: isRoot || isApex ? '#d9770640' : isTarget ? '#05966940' : isSpouse ? '#d9770630' : '#14514740',
                },
              ]}
            >
              <Ionicons
                name={isFemale ? 'woman' : 'person'}
                size={size * 0.54}
                color={isRoot || isApex ? '#fef08a' : isTarget ? '#a7f3d0' : '#fde68a'}
              />
            </View>
          )}
        </View>

        <Text
          style={[
            styles.nodeNameText,
            {
              color: isRoot || isApex ? '#fef08a' : isTarget ? '#ffffff' : isSpouse ? '#fef3c7' : '#e6f4ee',
              fontSize: isApex ? 12.5 : isRoot ? 12 : isTarget ? 11 : isSpouse ? 10 : 10.5,
              fontFamily: isRoot || isApex || isTarget ? 'Tajawal-Bold' : 'Tajawal-Medium',
              maxWidth: isApex || isRoot ? 116 : isTarget ? 80 : isSpouse ? 60 : 72,
            },
          ]}
          numberOfLines={1}
        >
          {name}
        </Text>
      </TouchableOpacity>
    );
  };

  const renderNodeWithSpouse = (
    person: any,
    type: 'root' | 'apex' | 'target' | 'normal' = 'normal',
    outerSide: 'left' | 'right' = 'right'
  ) => {
    if (!person) return null;
    const baseNode = renderAvatarNode(person, type);
    if (!person.spouse) {
      return (
        <View style={styles.spineNodeWrap} pointerEvents="box-none">
          {baseNode}
        </View>
      );
    }

    const spouse = person.spouse;
    const spouseRole = spouse.role || (spouse.gender === 'female' ? 'زوجة' : 'زوج');
    const spouseNode = renderAvatarNode(spouse, 'spouse');

    const connectorUnit = (
      <View style={styles.connectorUnit}>
        <View style={styles.spouseLine} />
        <View style={styles.spouseBadge}>
          <Text style={styles.spouseBadgeText}>{spouseRole}</Text>
        </View>
        <View style={styles.spouseLine} />
      </View>
    );

    return (
      <View style={styles.spineNodeWrap} pointerEvents="box-none">
        {/* Docked to the visual LEFT (outer side for left column) */}
        {outerSide === 'left' && (
          <View
            style={[styles.dockedSpouseWrap, styles.dockOuterLeft]}
            pointerEvents="box-none"
          >
            {spouseNode}
            {connectorUnit}
          </View>
        )}

        {/* Center bloodline member (strictly locked at column axis) */}
        {baseNode}

        {/* Docked to the visual RIGHT (outer side for right column) */}
        {outerSide === 'right' && (
          <View
            style={[styles.dockedSpouseWrap, styles.dockOuterRight]}
            pointerEvents="box-none"
          >
            {connectorUnit}
            {spouseNode}
          </View>
        )}
      </View>
    );
  };

  return (
    <Modal visible={visible} transparent animationType="slide" onRequestClose={onClose}>
      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        style={styles.overlay}
      >
        <TouchableOpacity
          activeOpacity={1}
          style={styles.backdropPressable}
          onPress={() => {
            Keyboard.dismiss();
            if (isSearchActive) {
              setIsSearchActive(false);
            }
          }}
        />
        <View style={[styles.modalCard, isSearchActive && styles.modalCardSearchActive, { backgroundColor: '#061814', borderColor: '#d9770650' }]}>

          {/* Header */}
          <View style={styles.header}>
            <View style={styles.headerTitleWrap}>
              <Text style={{ fontSize: 18 }}>🌴</Text>
              <Text style={styles.headerTitle}>صلة القرابة والنسب - عائلة السريّع</Text>
            </View>
            <TouchableOpacity onPress={onClose} style={styles.closeBtn}>
              <Ionicons name="close-circle" size={26} color="#9ca3af" />
            </TouchableOpacity>
          </View>

          {/* Search bar or switcher */}
          {targetPerson && (
            <View style={styles.compareHeaderBox}>
              <View style={{ flex: 1 }}>
                <Text style={styles.compareLabel}>
                  {mode === 'compare_with_me' ? 'مقارنة صلة القرابة بينك وبين:' : 'المقارَن الأول (المستهدف):'}
                </Text>
                <Text style={styles.compareTargetName}>
                  {targetPerson.full_name || targetPerson.first_name}
                </Text>
              </View>

              <View style={styles.headerButtonsRow}>
                <TouchableOpacity
                  style={styles.faceScanHeaderBtn}
                  onPress={() => {
                    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                    setIsFacePickerVisible(true);
                  }}
                  activeOpacity={0.8}
                >
                  <Ionicons name="camera" size={13} color="#061814" />
                  <Text style={styles.faceScanHeaderBtnText}>بالوجه 📸</Text>
                </TouchableOpacity>

                <TouchableOpacity
                  style={styles.switchModeBtn}
                  onPress={() => {
                    setIsSearchActive(!isSearchActive);
                    setSearchQuery('');
                    setSearchResults([]);
                  }}
                  activeOpacity={0.8}
                >
                  <Ionicons name="search" size={13} color="#fbbf24" />
                  <Text style={styles.switchModeText}>
                    {isSearchActive ? 'إلغاء' : mode === 'compare_with_me' ? 'بالاسم...' : 'طرف ثانٍ...'}
                  </Text>
                </TouchableOpacity>
              </View>
            </View>
          )}

          {/* Live Search Autocomplete Picker */}
          {isSearchActive ? (
            <View style={styles.searchPickerArea}>
              <View style={styles.searchInputWrap}>
                <Ionicons name="search" size={18} color="#fbbf24" />
                <TextInput
                  style={styles.searchInput}
                  placeholder="ابحث بالاسم أو التقط صورة..."
                  placeholderTextColor="#6ee7b760"
                  value={searchQuery}
                  onChangeText={handleSearchChange}
                  returnKeyType="search"
                />
                {searchQuery.length > 0 && (
                  <TouchableOpacity
                    onPress={() => handleSearchChange('')}
                    hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
                  >
                    <Ionicons name="close-circle" size={18} color="#9ca3af" />
                  </TouchableOpacity>
                )}
                <TouchableOpacity
                  style={styles.inputCameraIconBtn}
                  onPress={() => {
                    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                    setIsFacePickerVisible(true);
                  }}
                  hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
                >
                  <Ionicons name="camera" size={19} color="#fbbf24" />
                </TouchableOpacity>
              </View>

              {faceErrorMessage && (
                <View style={styles.faceErrorBanner}>
                  <Ionicons name="information-circle" size={15} color="#fbbf24" />
                  <Text style={styles.faceErrorText}>{faceErrorMessage}</Text>
                </View>
              )}

              {loadingPersons ? (
                <View style={{ paddingVertical: 24, alignItems: 'center', gap: 8 }}>
                  <ActivityIndicator size="small" color="#fbbf24" />
                  <Text style={{ color: '#fbbf24', fontSize: 12, fontFamily: 'Tajawal-Medium' }}>
                    جاري تحميل قائمة أفراد العائلة...
                  </Text>
                </View>
              ) : searchQuery.trim().length > 0 && searchResults.length === 0 ? (
                <View style={{ paddingVertical: 24, alignItems: 'center' }}>
                  <Text style={{ color: '#9ca3af', fontSize: 12, fontFamily: 'Tajawal-Regular' }}>
                    {`لا توجد نتائج مطابقة لـ "${searchQuery}"`}
                  </Text>
                </View>
              ) : (
                <ScrollView
                  style={{ maxHeight: 240, marginTop: 8 }}
                  keyboardShouldPersistTaps="handled"
                  showsVerticalScrollIndicator={true}
                >
                  {searchResults.map((p) => (
                    <TouchableOpacity
                      key={p.id}
                      style={styles.searchResultRow}
                      onPress={() => handleSelectPerson(p)}
                    >
                      <Ionicons name={p.gender === 'female' ? 'woman' : 'man'} size={18} color="#fbbf24" />
                      <Text style={styles.searchResultName}>{p.full_name || p.first_name || p.name}</Text>
                      <Ionicons name="chevron-back" size={14} color="#6ee7b7" />
                    </TouchableOpacity>
                  ))}
                </ScrollView>
              )}
            </View>
          ) : loading ? (
            <View style={styles.centerBox}>
              <ActivityIndicator size="large" color="#fbbf24" />
              <Text style={styles.loadingText}>جاري بناء شجرة النسب والقرابة...</Text>
            </View>
          ) : errorMsg ? (
            <View style={styles.centerBox}>
              <Ionicons name="alert-circle" size={44} color="#f87171" />
              <Text style={styles.errorText}>{errorMsg}</Text>
            </View>
          ) : kinshipData ? (
            <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={styles.scrollContent}>

              {/* 💡 Interactive hint for tapping members */}
              <View style={styles.interactiveTipWrap}>
                <Ionicons name="finger-print" size={14} color="#fbbf24" />
                <Text style={styles.interactiveTipText}>
                  انقر على أي شخص لعرض بطاقته وأبنائه في شجرة العائلة
                </Text>
              </View>

              {/* 📸 Capturable Tree Card Area with Gradient */}
              <View ref={treeCardRef} collapsable={false}>
                <LinearGradient
                  colors={['#0b2f27', '#071f1a', '#04110e']}
                  style={styles.captureCard}
                  onLayout={(e) => {
                    const w = Math.round(e.nativeEvent.layout.width);
                    if (w > 0 && Math.abs(w - measuredCardWidth) > 2) {
                      setMeasuredCardWidth(w);
                    }
                  }}
                >
                  {/* 🌳 Visual Lineage Tree */}
                  <View style={styles.treeSection}>
                    {isUnrelated ? (
                      <View style={styles.unrelatedCard}>
                        <View style={styles.unrelatedRow}>
                          {renderAvatarNode(kinshipData.person_a, 'target')}
                          <View style={styles.unrelatedMidWrap}>
                            <Ionicons name="close-circle-outline" size={24} color="#9ca3af" />
                            <Text style={styles.unrelatedBadgeText}>لا توجد صلة</Text>
                          </View>
                          {renderAvatarNode(kinshipData.person_b, 'target')}
                        </View>
                        <Text style={styles.unrelatedDescText}>
                          {relationship?.summary_text || 'هذا الشخص من خارج العائلة ولا توجد صلة نسب أو مصاهرة مسجلة تربطه بالطرف الآخر.'}
                        </Text>
                      </View>
                    ) : isAffinalSpouse ? (
                      <View style={styles.forkTreeContainer}>
                        {(() => {
                          let affinalUpper = (upperChain || []).filter(Boolean);
                          const hasFamilyRoot = affinalUpper.some(
                            (node: any) =>
                              node.id === 0 ||
                              node.is_virtual_family_root ||
                              node.name === 'السريّع' ||
                              node.name === 'عائلة السريّع' ||
                              node.name === 'السريّع' ||
                              node.name === 'عائلة السريّع' ||
                              node.name === 'السريع' ||
                              node.name === 'عائلة السريع'
                          );
                          if (!hasFamilyRoot) {
                            affinalUpper = [
                              { id: 0, name: 'السريّع', full_name: 'السريّع', is_virtual_family_root: true },
                              ...affinalUpper,
                            ];
                          }
                          return (
                            <View style={styles.directColumn}>
                              {affinalUpper.map((node: any, idx: number) => {
                                const isRoot =
                                  node.id === 0 ||
                                  node.is_virtual_family_root ||
                                  node.name === 'السريّع' ||
                                  node.full_name === 'السريّع' ||
                                  node.name === 'عائلة السريّع' ||
                                  node.full_name === 'عائلة السريّع' ||
                                  node.name === 'السريّع' ||
                                  node.full_name === 'السريّع' ||
                                  node.name === 'عائلة السريّع' ||
                                  node.full_name === 'عائلة السريّع' ||
                                  node.name === 'السريع' ||
                                  node.full_name === 'السريع' ||
                                  node.name === 'عائلة السريع' ||
                                  node.full_name === 'عائلة السريع';
                                return (
                                  <React.Fragment key={node.id || `up_${idx}`}>
                                    {renderAvatarNode(node, isRoot ? 'root' : 'normal')}
                                    <View style={styles.directLine} />
                                  </React.Fragment>
                                );
                              })}
                            </View>
                          );
                        })()}
                        {cleanFamilyChain.slice(0, -1).map((node: any, idx: number) => (
                          <React.Fragment key={node.id || `fam_${idx}`}>
                            {renderAvatarNode(node, idx === 0 ? 'apex' : 'normal')}
                            <View style={styles.directLine} />
                          </React.Fragment>
                        ))}
                        <View style={styles.nodeWithSpouseRow}>
                          {renderAvatarNode(familyTargetNode, 'target')}
                          <View style={styles.connectorUnit}>
                            <View style={styles.spouseLine} />
                            <View style={styles.spouseBadge}>
                              <Text style={styles.spouseBadgeText}>
                                {spouseTargetNode?.gender === 'female' ? 'زوجة' : 'زوج'}
                              </Text>
                            </View>
                            <View style={styles.spouseLine} />
                          </View>
                          {renderAvatarNode(spouseTargetNode, 'spouse')}
                        </View>
                      </View>
                    ) : isDirect ? (
                      <View style={styles.directColumn}>
                        {(() => {
                          let directUpper = (upperChain || []).filter(Boolean);
                          const hasFamilyRoot = directUpper.some(
                            (node: any) =>
                              node.id === 0 ||
                              node.is_virtual_family_root ||
                              node.name === 'السريّع' ||
                              node.name === 'عائلة السريّع' ||
                              node.name === 'السريّع' ||
                              node.name === 'عائلة السريّع' ||
                              node.name === 'السريع' ||
                              node.name === 'عائلة السريع'
                          );
                          if (!hasFamilyRoot) {
                            directUpper = [
                              { id: 0, name: 'السريّع', full_name: 'السريّع', is_virtual_family_root: true },
                              ...directUpper,
                            ];
                          }
                          return directUpper.map((node: any, idx: number) => {
                            const isRoot =
                              node.id === 0 ||
                              node.is_virtual_family_root ||
                              node.name === 'السريّع' ||
                              node.full_name === 'السريّع' ||
                              node.name === 'عائلة السريّع' ||
                              node.full_name === 'عائلة السريّع' ||
                              node.name === 'السريع' ||
                              node.full_name === 'السريع' ||
                              node.name === 'عائلة السريع' ||
                              node.full_name === 'عائلة السريع';
                            return (
                              <React.Fragment key={node.id || `up_${idx}`}>
                                {renderAvatarNode(node, isRoot ? 'root' : 'normal')}
                                <View style={styles.directLine} />
                              </React.Fragment>
                            );
                          });
                        })()}
                        {(kinshipData?.path || []).map((step: any, idx: number) => {
                          const isLast = idx === (kinshipData?.path || []).length - 1;
                          return (
                            <React.Fragment key={step.id || idx}>
                              {renderNodeWithSpouse(step, idx === 0 ? 'apex' : isLast ? 'target' : 'normal', 'left')}
                              {!isLast && <View style={styles.directLine} />}
                            </React.Fragment>
                          );
                        })}
                      </View>
                    ) : (
                      /* Collateral, In-Law, or Disjoint/Virtual Root */
                      <View style={styles.forkTreeContainer}>
                        {(() => {
                          const isAncestorVirtual =
                            !ancestor ||
                            ancestor.id === 0 ||
                            ancestor.is_virtual_family_root ||
                            ancestor.name === 'السريّع' ||
                            ancestor.name === 'عائلة السريّع' ||
                            ancestor.name === 'السريّع' ||
                            ancestor.name === 'عائلة السريّع' ||
                            ancestor.name === 'السريع' ||
                            ancestor.name === 'عائلة السريع';
                          let chainToRender = (upperChain || []).filter((node: any) => {
                            if (!node) return false;
                            // If ancestor itself is virtual root, do not duplicate it above itself
                            if (
                              isAncestorVirtual &&
                              (node.id === 0 ||
                                node.is_virtual_family_root ||
                                node.name === 'السريّع' ||
                                node.first_name === 'السريّع' ||
                                node.name === 'عائلة السريّع' ||
                                node.first_name === 'عائلة السريّع' ||
                                node.name === 'السريّع' ||
                                node.first_name === 'السريّع' ||
                                node.name === 'عائلة السريّع' ||
                                node.first_name === 'عائلة السريّع' ||
                                node.name === 'السريع' ||
                                node.first_name === 'السريع' ||
                                node.name === 'عائلة السريع' ||
                                node.first_name === 'عائلة السريع')
                            ) {
                              return false;
                            }
                            if (ancestor && node.id === ancestor.id) {
                              return false;
                            }
                            return true;
                          });

                          // Ensure the family root is present above real common ancestors
                          if (!isAncestorVirtual) {
                            const hasFamilyRoot = chainToRender.some(
                              (node: any) =>
                                node.id === 0 ||
                                node.is_virtual_family_root ||
                                node.name === 'السريّع' ||
                                node.name === 'عائلة السريّع' ||
                                node.name === 'السريع' ||
                                node.name === 'عائلة السريع'
                            );
                            if (!hasFamilyRoot) {
                              chainToRender = [
                                { id: 0, name: 'السريّع', full_name: 'السريّع', is_virtual_family_root: true },
                                ...chainToRender,
                              ];
                            }
                          }

                          return chainToRender.length > 0 ? (
                            <View style={styles.directColumn}>
                              {chainToRender.map((node: any, idx: number) => {
                                const isRoot =
                                  node.id === 0 ||
                                  node.is_virtual_family_root ||
                                  node.name === 'السريّع' ||
                                  node.full_name === 'السريّع' ||
                                  node.name === 'عائلة السريّع' ||
                                  node.full_name === 'عائلة السريّع' ||
                                  node.name === 'السريّع' ||
                                  node.full_name === 'السريّع' ||
                                  node.name === 'عائلة السريّع' ||
                                  node.full_name === 'عائلة السريّع' ||
                                  node.name === 'السريع' ||
                                  node.full_name === 'السريع' ||
                                  node.name === 'عائلة السريع' ||
                                  node.full_name === 'عائلة السريع';
                                return (
                                  <React.Fragment key={node.id || `up_${idx}`}>
                                    {renderAvatarNode(node, isRoot ? 'root' : 'normal')}
                                    <View style={styles.directLine} />
                                  </React.Fragment>
                                );
                              })}
                            </View>
                          ) : null;
                        })()}
                        {ancestor && (
                          <View style={styles.apexWrap}>
                            {renderAvatarNode(ancestor, isVirtualRoot ? 'root' : 'apex')}
                          </View>
                        )}
                        <View style={styles.svgArchWrap}>
                          <Svg height="36" width="100%" viewBox={`0 0 ${cardWidth} 36`}>
                            <Defs>
                              <SvgLinearGradient id="goldArch" x1="0" y1="0" x2="1" y2="0">
                                <Stop offset="0" stopColor="#34d399" stopOpacity="0.9" />
                                <Stop offset="0.2" stopColor="#fbbf24" stopOpacity="1" />
                                <Stop offset="0.5" stopColor="#f59e0b" stopOpacity="1" />
                                <Stop offset="0.8" stopColor="#fbbf24" stopOpacity="1" />
                                <Stop offset="1" stopColor="#34d399" stopOpacity="0.9" />
                              </SvgLinearGradient>
                            </Defs>
                            <Path
                              d={`M ${centerX} 0 C ${centerX} 18, ${leftX} 14, ${leftX} 36 M ${centerX} 0 C ${centerX} 18, ${rightX} 14, ${rightX} 36`}
                              stroke="url(#goldArch)"
                              strokeWidth="2.5"
                              fill="none"
                              strokeLinecap="round"
                            />
                          </Svg>
                        </View>
                        <View style={[styles.branchesRow, { gap: colGap }]} pointerEvents="box-none">
                          <View style={styles.branchCol} pointerEvents="box-none">
                            {cleanBranchA.map((node: any, idx: number) => {
                              const isTarget = idx === cleanBranchA.length - 1;
                              return (
                                <React.Fragment key={node.id || idx}>
                                  {renderNodeWithSpouse(node, isTarget ? 'target' : 'normal', 'left')}
                                  {!isTarget && <View style={styles.stemLine} />}
                                </React.Fragment>
                              );
                            })}
                          </View>
                          <View style={styles.branchCol} pointerEvents="box-none">
                            {cleanBranchB.map((node: any, idx: number) => {
                              const isTarget = idx === cleanBranchB.length - 1;
                              return (
                                <React.Fragment key={node.id || idx}>
                                  {renderNodeWithSpouse(node, isTarget ? 'target' : 'normal', 'right')}
                                  {!isTarget && <View style={styles.stemLine} />}
                                </React.Fragment>
                              );
                            })}
                          </View>
                        </View>
                      </View>
                    )}
                  </View>

                  {/* 👑 Golden Relationship Title Ribbon & Summary */}
                  {/* {relationship?.title ? (
                    <View style={styles.ribbonContainer}>
                      <LinearGradient
                        colors={['#b45309', '#f59e0b', '#b45309']}
                        start={{ x: 0, y: 0 }}
                        end={{ x: 1, y: 0 }}
                        style={styles.ribbonBadge}
                      >
                        <Text style={styles.ribbonText}>{relationship.title}</Text>
                      </LinearGradient>
                      {relationship?.summary_text ? (
                        <Text style={styles.summaryText}>{relationship.summary_text}</Text>
                      ) : null}
                    </View>
                  ) : null} */}

                  {/* Footer & Watermark */}
                  <View style={styles.cardFooter}>
                    <Text style={styles.watermarkText}>تطبيق ومنصة عائلة السريّع 🌴</Text>
                  </View>
                </LinearGradient>
              </View>

              {/* 📸 Screenshot Share Button */}
              <TouchableOpacity
                style={[styles.shareBtn, sharingImg && { opacity: 0.7 }]}
                onPress={handleShareImage}
                disabled={sharingImg}
                activeOpacity={0.88}
              >
                <LinearGradient
                  colors={['#25D366', '#128C7E']}
                  start={{ x: 0, y: 0 }}
                  end={{ x: 1, y: 0 }}
                  style={styles.shareBtnGradient}
                >
                  {sharingImg ? (
                    <ActivityIndicator size="small" color="#fff" />
                  ) : (
                    <Ionicons name="share-social" size={20} color="#fff" />
                  )}
                  <Text style={styles.shareBtnText}>
                    {sharingImg ? 'جاري تجهيز الصورة...' : 'مشاركة صلة القرابة (صورة 📸)'}
                  </Text>
                </LinearGradient>
              </TouchableOpacity>
            </ScrollView>
          ) : (
            <View style={styles.centerBox}>
              <Text style={{ color: '#9ca3af', fontFamily: 'Tajawal-Regular' }}>
                ابحث واختر الشخص الثاني لمقارنة القرابة.
              </Text>
            </View>
          )}

          {/* Scanning Indicator Overlay */}
          {isFaceScanning && (
            <View style={styles.scanningOverlay}>
              <View style={styles.scanningCard}>
                <ActivityIndicator size="large" color="#fbbf24" />
                <Text style={styles.scanningTitle}>جاري التعرف على ملامح الوجه...</Text>
                <Text style={styles.scanningSubtitle}>
                  يتم فحص الصورة ومطابقتها مع شجرة عائلة السريّع
                </Text>
              </View>
            </View>
          )}

          {/* Modal: Face Picker (Camera vs Gallery) */}
          <Modal
            visible={isFacePickerVisible}
            transparent
            animationType="fade"
            onRequestClose={() => setIsFacePickerVisible(false)}
          >
            <TouchableOpacity
              style={styles.facePickerModalOverlay}
              activeOpacity={1}
              onPress={() => setIsFacePickerVisible(false)}
            >
              <View style={styles.facePickerCard} onStartShouldSetResponder={() => true}>
                <View style={styles.facePickerIconWrap}>
                  <Ionicons name="camera" size={30} color="#fbbf24" />
                </View>
                <Text style={styles.facePickerTitle}>التعرف بملامح الوجه 📸</Text>
                <Text style={styles.facePickerSubtitle}>
                  صوّر الشخص أو اختر صورته من المعرض لمطابقتها مع شجرة عائلة السريّع وحساب صلة القرابة فوراً.
                </Text>

                <TouchableOpacity
                  style={[styles.facePickerActionBtn, styles.facePickerActionBtnPrimary]}
                  onPress={onTakePhoto}
                  activeOpacity={0.8}
                >
                  <Ionicons name="camera-reverse" size={18} color="#061814" />
                  <Text style={styles.facePickerActionTextPrimary}>التقاط صورة بالكاميرا</Text>
                </TouchableOpacity>

                <TouchableOpacity
                  style={styles.facePickerActionBtn}
                  onPress={onPickImage}
                  activeOpacity={0.8}
                >
                  <Ionicons name="images" size={18} color="#fbbf24" />
                  <Text style={styles.facePickerActionText}>اختيار صورة من المعرض</Text>
                </TouchableOpacity>

                <TouchableOpacity
                  style={styles.facePickerCancelBtn}
                  onPress={() => setIsFacePickerVisible(false)}
                  hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
                >
                  <Text style={styles.facePickerCancelText}>إلغاء</Text>
                </TouchableOpacity>
              </View>
            </TouchableOpacity>
          </Modal>

          {/* Modal: Multiple Matches Selection */}
          <Modal
            visible={isMultipleMatchesVisible}
            transparent
            animationType="slide"
            onRequestClose={() => setIsMultipleMatchesVisible(false)}
          >
            <View style={styles.matchesModalOverlay}>
              <View style={styles.matchesModalCard}>
                <View style={styles.matchesModalHeader}>
                  <Text style={styles.matchesModalTitle}>نتائج التعرف على الوجه 🎯</Text>
                  <Text style={styles.matchesModalSubtitle}>
                    تم العثور على أكثر من شخص مقارب، اختر الشخص المطلوب لبدء حساب صلة القرابة:
                  </Text>
                </View>

                <ScrollView style={{ maxHeight: 280 }} showsVerticalScrollIndicator={false}>
                  {candidateMatches.map((m, idx) => (
                    <TouchableOpacity
                      key={m.person.id || idx}
                      style={styles.matchRow}
                      onPress={() => handleSelectPerson(m.person)}
                      activeOpacity={0.7}
                    >
                      {m.person.photo_url ? (
                        <Image
                          source={{ uri: m.person.photo_url }}
                          style={styles.matchAvatar}
                          contentFit="cover"
                        />
                      ) : (
                        <View style={[styles.matchAvatar, { alignItems: 'center', justifyContent: 'center' }]}>
                          <Ionicons name="person" size={20} color="#fbbf24" />
                        </View>
                      )}
                      <View style={styles.matchInfo}>
                        <Text style={styles.matchName}>{m.person.full_name || m.person.first_name}</Text>
                        <View style={styles.matchSimilarityBadge}>
                          <Text style={styles.matchSimilarityText}>
                            نسبة التطابق: {m.similarity}
                          </Text>
                        </View>
                      </View>
                      <Ionicons name="chevron-back" size={16} color="#fbbf24" />
                    </TouchableOpacity>
                  ))}
                </ScrollView>

                <TouchableOpacity
                  style={styles.facePickerCancelBtn}
                  onPress={() => setIsMultipleMatchesVisible(false)}
                  hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
                >
                  <Text style={styles.facePickerCancelText}>إلغاء</Text>
                </TouchableOpacity>
              </View>
            </View>
          </Modal>
        </View>
      </KeyboardAvoidingView>
    </Modal>
  );
};

const styles = StyleSheet.create({
  overlay: { flex: 1, backgroundColor: 'rgba(0,0,0,0.85)', justifyContent: 'flex-end' },
  backdropPressable: { position: 'absolute', top: 0, bottom: 0, left: 0, right: 0 },
  modalCard: {
    borderTopLeftRadius: 28,
    borderTopRightRadius: 28,
    borderWidth: 1.5,
    maxHeight: '92%',
    padding: 16,
  },
  modalCardSearchActive: {
    minHeight: 320,
    maxHeight: '85%',
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingBottom: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: 'rgba(245, 158, 11, 0.25)',
  },
  headerTitleWrap: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  headerTitle: { fontSize: 16, fontFamily: 'Tajawal-Bold', color: '#fef3c7' },
  closeBtn: { padding: 2 },
  compareHeaderBox: {
    padding: 10,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: 'rgba(245, 158, 11, 0.3)',
    backgroundColor: 'rgba(255, 255, 255, 0.05)',
    marginTop: 10,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  compareLabel: { fontSize: 11, fontFamily: 'Tajawal-Regular', color: '#9ca3af' },
  compareTargetName: { fontSize: 13, fontFamily: 'Tajawal-Bold', color: '#fbbf24', marginTop: 2 },
  switchModeBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 14,
    backgroundColor: 'rgba(245, 158, 11, 0.2)',
    borderWidth: 1,
    borderColor: 'rgba(245, 158, 11, 0.4)',
  },
  switchModeText: { fontSize: 11, fontFamily: 'Tajawal-Bold', color: '#fbbf24' },
  searchPickerArea: { marginTop: 10 },
  searchInputWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#fbbf24',
    backgroundColor: '#041511',
  },
  searchInput: {
    flex: 1,
    fontSize: 13,
    fontFamily: 'Tajawal-Medium',
    color: '#ffffff',

    writingDirection: 'rtl',
    paddingVertical: Platform.OS === 'ios' ? 6 : 4,
  },
  searchResultRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingVertical: 10,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: 'rgba(255, 255, 255, 0.1)',
  },
  searchResultName: { flex: 1, fontSize: 13, fontFamily: 'Tajawal-Bold', color: '#ffffff' },
  centerBox: { paddingVertical: 40, alignItems: 'center', justifyContent: 'center', gap: 12 },
  loadingText: { fontSize: 13, fontFamily: 'Tajawal-Bold', textAlign: 'center', color: '#fbbf24' },
  errorText: { fontSize: 13, fontFamily: 'Tajawal-Bold', textAlign: 'center', color: '#f87171' },
  scrollContent: { paddingVertical: 10, gap: 12 },

  // 📸 Capturable Tree Card with LinearGradient
  captureCard: {
    borderRadius: 24,
    padding: 16,
    borderWidth: 1.5,
    borderColor: '#d97706',
    overflow: 'hidden',
  },
  cardHeader: {
    alignItems: 'center',
    marginBottom: 6,
  },
  cardBadge: {
    backgroundColor: 'rgba(245, 158, 11, 0.25)',
    borderWidth: 1,
    borderColor: 'rgba(251, 191, 36, 0.5)',
    paddingHorizontal: 14,
    paddingVertical: 3,
    borderRadius: 14,
  },
  cardBadgeText: {
    color: '#fef08a',
    fontSize: 11,
    fontFamily: 'Tajawal-Bold',
  },
  treeSection: {
    direction: 'ltr',
    paddingVertical: 4,
  },
  directColumn: {
    alignItems: 'center',
    gap: 4,
  },
  directLine: {
    width: 2.5,
    height: 22,
    borderRadius: 2,
    backgroundColor: '#fbbf24',
  },
  forkTreeContainer: {
    direction: 'ltr',
    alignItems: 'center',
  },
  apexWrap: {
    alignItems: 'center',
    marginBottom: 2,
  },
  rootBadgeWrap: {
    backgroundColor: 'rgba(245, 158, 11, 0.25)',
    borderWidth: 1,
    borderColor: 'rgba(251, 191, 36, 0.5)',
    paddingHorizontal: 10,
    paddingVertical: 2,
    borderRadius: 12,
    marginBottom: 3,
  },
  rootBadgeText: {
    color: '#fef08a',
    fontSize: 9.5,
    fontFamily: 'Tajawal-Bold',
  },
  crownBadge: {
    marginBottom: -4,
    zIndex: 2,
  },
  crownIcon: {
    fontSize: 20,
  },
  svgArchWrap: {
    width: '100%',
    height: 38,
    alignItems: 'center',
    justifyContent: 'center',
    marginVertical: -2,
  },
  branchesRow: {
    direction: 'ltr',
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'flex-start',
    width: '100%',
  },
  branchCol: {
    width: 68,
    alignItems: 'center',
  },
  stemLine: {
    width: 2,
    height: 18,
    marginVertical: 1.5,
    borderRadius: 1,
    backgroundColor: '#34d39980',
  },
  nodeWrapper: {
    alignItems: 'center',
    marginVertical: 1,
  },
  avatarBorder: {
    borderWidth: 2,
    alignItems: 'center',
    justifyContent: 'center',
    overflow: 'hidden',
  },
  apexBorderGlow: {
    borderWidth: 2.5,
    ...Platform.select({
      ios: {
        shadowColor: '#fbbf24',
        shadowOffset: { width: 0, height: 0 },
        shadowOpacity: 0.8,
        shadowRadius: 10,
      },
      android: {
        elevation: 8,
      },
    }),
  },
  targetBorderGlow: {
    borderWidth: 2,
    ...Platform.select({
      ios: {
        shadowColor: '#34d399',
        shadowOffset: { width: 0, height: 0 },
        shadowOpacity: 0.8,
        shadowRadius: 8,
      },
      android: {
        elevation: 6,
      },
    }),
  },
  spouseBorderGlow: {
    borderWidth: 1.5,
    ...Platform.select({
      ios: {
        shadowColor: '#fbbf24',
        shadowOffset: { width: 0, height: 0 },
        shadowOpacity: 0.6,
        shadowRadius: 6,
      },
      android: {
        elevation: 4,
      },
    }),
  },
  avatarPlaceholder: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  nodeNameText: {
    textAlign: 'center',
    marginTop: 3,
    lineHeight: 14,
  },
  ribbonContainer: {
    alignItems: 'center',
    marginTop: 10,
    marginBottom: 4,
    gap: 4,
  },
  ribbonBadge: {
    paddingHorizontal: 16,
    paddingVertical: 5,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#fef08a',
    ...Platform.select({
      ios: {
        shadowColor: '#d97706',
        shadowOffset: { width: 0, height: 3 },
        shadowOpacity: 0.5,
        shadowRadius: 6,
      },
      android: {
        elevation: 5,
      },
    }),
  },
  ribbonText: {
    color: '#1a1102',
    fontSize: 13,
    fontFamily: 'Tajawal-Bold',
    textAlign: 'center',
  },
  cardFooter: {
    marginTop: 8,
    paddingTop: 6,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: 'rgba(255, 255, 255, 0.15)',
    alignItems: 'center',
    gap: 3,
  },
  summaryText: {
    color: 'rgba(255, 255, 255, 0.85)',
    fontSize: 11,
    fontFamily: 'Tajawal-Regular',
    textAlign: 'center',
    lineHeight: 16,
    paddingHorizontal: 6,
  },
  watermarkText: {
    color: '#fbbf24',
    fontSize: 10,
    fontFamily: 'Tajawal-Bold',
    marginTop: 2,
  },
  shareBtn: {
    borderRadius: 16,
    overflow: 'hidden',
    marginTop: 4,
    ...Platform.select({
      ios: {
        shadowColor: '#25D366',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.35,
        shadowRadius: 8,
      },
      android: {
        elevation: 4,
      },
    }),
  },
  shareBtnGradient: {
    flexDirection: 'row',
    paddingVertical: 14,
    paddingHorizontal: 16,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  shareBtnText: { color: '#fff', fontSize: 13, fontFamily: 'Tajawal-Bold' },
  unrelatedCard: {
    padding: 16,
    borderRadius: 20,
    backgroundColor: 'rgba(255, 255, 255, 0.05)',
    borderWidth: 1,
    borderColor: 'rgba(255, 255, 255, 0.1)',
    alignItems: 'center',
    gap: 12,
  },
  unrelatedRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 16,
  },
  unrelatedMidWrap: {
    alignItems: 'center',
    gap: 4,
  },
  unrelatedBadgeText: {
    fontSize: 10,
    fontFamily: 'Tajawal-Bold',
    color: '#9ca3af',
  },
  unrelatedDescText: {
    fontSize: 11.5,
    fontFamily: 'Tajawal-Regular',
    color: '#d1d5db',
    textAlign: 'center',
    lineHeight: 18,
  },
  nodeWithSpouseRow: {
    direction: 'ltr',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    marginVertical: 2,
    gap: 4,
  },
  spineNodeWrap: {
    direction: 'ltr',
    width: 68,
    alignItems: 'center',
    justifyContent: 'center',
    position: 'relative',
    marginVertical: 1,
  },
  dockedSpouseWrap: {
    direction: 'ltr',
    position: 'absolute',
    top: 2,
    flexDirection: 'row',
    alignItems: 'center',
    zIndex: 20,
    elevation: 4,
  },
  dockOuterLeft: {
    right: 58,
  },
  dockOuterRight: {
    left: 58,
  },
  connectorUnit: {
    direction: 'ltr',
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 2,
    gap: 2,
  },
  spouseConnectorWrap: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 2,
  },
  spouseBadge: {
    backgroundColor: 'rgba(251, 191, 36, 0.22)',
    borderWidth: 0.8,
    borderColor: 'rgba(251, 191, 36, 0.55)',
    paddingHorizontal: 4,
    paddingVertical: 1,
    borderRadius: 6,
  },
  spouseBadgeText: {
    color: '#fef08a',
    fontSize: 8,
    fontFamily: 'Tajawal-Bold',
  },
  spouseLine: {
    width: 6,
    height: 1.5,
    backgroundColor: '#fbbf24',
    borderRadius: 1,
  },
  interactiveTipWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    paddingVertical: 7,
    paddingHorizontal: 14,
    borderRadius: 14,
    backgroundColor: 'rgba(251, 191, 36, 0.12)',
    borderWidth: 1,
    borderColor: 'rgba(251, 191, 36, 0.28)',
    marginBottom: 8,
  },
  interactiveTipText: {
    color: '#fef08a',
    fontSize: 11,
    fontFamily: 'Tajawal-Bold',
  },
  headerButtonsRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  faceScanHeaderBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 14,
    backgroundColor: '#fbbf24',
    borderWidth: 1,
    borderColor: '#fef08a',
  },
  faceScanHeaderBtnText: {
    fontSize: 11,
    fontFamily: 'Tajawal-Bold',
    color: '#061814',
  },
  inputCameraIconBtn: {
    padding: 4,
  },
  faceErrorBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: 'rgba(239, 68, 68, 0.12)',
    borderWidth: 1,
    borderColor: 'rgba(239, 68, 68, 0.3)',
    borderRadius: 10,
    paddingHorizontal: 10,
    paddingVertical: 6,
    marginTop: 6,
  },
  faceErrorText: {
    fontSize: 11,
    fontFamily: 'Tajawal-Regular',
    color: '#fca5a5',
    flex: 1,
  },
  facePickerModalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.78)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  facePickerCard: {
    width: '100%',
    maxWidth: 360,
    backgroundColor: '#071e19',
    borderRadius: 24,
    borderWidth: 1.5,
    borderColor: 'rgba(251, 191, 36, 0.4)',
    padding: 22,
    alignItems: 'center',
    gap: 12,
  },
  facePickerIconWrap: {
    width: 60,
    height: 60,
    borderRadius: 30,
    backgroundColor: 'rgba(251, 191, 36, 0.12)',
    borderWidth: 1.5,
    borderColor: 'rgba(251, 191, 36, 0.35)',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 4,
  },
  facePickerTitle: {
    fontSize: 16,
    fontFamily: 'Tajawal-Bold',
    color: '#fef3c7',
    textAlign: 'center',
  },
  facePickerSubtitle: {
    fontSize: 12,
    fontFamily: 'Tajawal-Regular',
    color: '#9ca3af',
    textAlign: 'center',
    lineHeight: 18,
    marginBottom: 4,
  },
  facePickerActionBtn: {
    width: '100%',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 12,
    borderRadius: 16,
    backgroundColor: 'rgba(251, 191, 36, 0.12)',
    borderWidth: 1,
    borderColor: 'rgba(251, 191, 36, 0.35)',
  },
  facePickerActionBtnPrimary: {
    backgroundColor: '#fbbf24',
    borderColor: '#fef08a',
  },
  facePickerActionText: {
    fontSize: 13,
    fontFamily: 'Tajawal-Bold',
    color: '#fbbf24',
  },
  facePickerActionTextPrimary: {
    fontSize: 13,
    fontFamily: 'Tajawal-Bold',
    color: '#061814',
  },
  facePickerCancelBtn: {
    paddingVertical: 8,
    paddingHorizontal: 16,
    marginTop: 2,
  },
  facePickerCancelText: {
    fontSize: 12,
    fontFamily: 'Tajawal-Medium',
    color: '#9ca3af',
  },
  scanningOverlay: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: 'rgba(4, 21, 17, 0.92)',
    zIndex: 50,
    justifyContent: 'center',
    alignItems: 'center',
    borderTopLeftRadius: 28,
    borderTopRightRadius: 28,
  },
  scanningCard: {
    alignItems: 'center',
    gap: 12,
    padding: 24,
  },
  scanningTitle: {
    fontSize: 15,
    fontFamily: 'Tajawal-Bold',
    color: '#fbbf24',
    textAlign: 'center',
  },
  scanningSubtitle: {
    fontSize: 12,
    fontFamily: 'Tajawal-Regular',
    color: '#9ca3af',
    textAlign: 'center',
  },
  matchesModalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.82)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  matchesModalCard: {
    width: '100%',
    maxWidth: 380,
    backgroundColor: '#071e19',
    borderRadius: 24,
    borderWidth: 1.5,
    borderColor: 'rgba(251, 191, 36, 0.4)',
    padding: 20,
    maxHeight: '80%',
  },
  matchesModalHeader: {
    marginBottom: 14,
    alignItems: 'center',
    gap: 4,
  },
  matchesModalTitle: {
    fontSize: 16,
    fontFamily: 'Tajawal-Bold',
    color: '#fbbf24',
  },
  matchesModalSubtitle: {
    fontSize: 12,
    fontFamily: 'Tajawal-Regular',
    color: '#9ca3af',
    textAlign: 'center',
  },
  matchRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 10,
    paddingHorizontal: 12,
    borderRadius: 14,
    backgroundColor: 'rgba(255, 255, 255, 0.05)',
    borderWidth: 1,
    borderColor: 'rgba(251, 191, 36, 0.2)',
    marginBottom: 8,
    gap: 10,
  },
  matchAvatar: {
    width: 44,
    height: 44,
    borderRadius: 22,
    borderWidth: 1.5,
    borderColor: '#fbbf24',
    backgroundColor: '#061814',
  },
  matchInfo: {
    flex: 1,
  },
  matchName: {
    fontSize: 13,
    fontFamily: 'Tajawal-Bold',
    color: '#fef3c7',
  },
  matchSimilarityBadge: {
    marginTop: 3,
    alignSelf: 'flex-start',
    backgroundColor: 'rgba(52, 211, 153, 0.2)',
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 6,
  },
  matchSimilarityText: {
    fontSize: 10,
    fontFamily: 'Tajawal-Bold',
    color: '#34d399',
  },
});

