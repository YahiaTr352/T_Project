import { Colors, silaTheme } from '@/constants/theme';
import MaterialIcons from '@expo/vector-icons/MaterialIcons';
import * as Haptics from 'expo-haptics';
import { useMemo, useState } from 'react';
import {
  FlatList,
  Modal,
  Platform,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  TouchableWithoutFeedback,
  View,
  useColorScheme,
} from 'react-native';
import { ThemedText } from '../themed-text';
import {
  COUNTRIES,
  CountryCodeItem,
  FREQUENT_COUNTRIES,
  toWesternDigits,
} from './countryData';

interface CountryPickerModalProps {
  visible: boolean;
  selectedCountry: CountryCodeItem;
  onSelect: (country: CountryCodeItem) => void;
  onClose: () => void;
}

export function CountryPickerModal({
  visible,
  selectedCountry,
  onSelect,
  onClose,
}: CountryPickerModalProps) {
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';
  const colors = Colors[colorScheme ?? 'light'];

  const [searchQuery, setSearchQuery] = useState('');

  const filteredCountries = useMemo(() => {
    const q = toWesternDigits(searchQuery.trim().toLowerCase());
    if (!q) return COUNTRIES;

    return COUNTRIES.filter((item) => {
      const matchNameAr = item.nameAr.toLowerCase().includes(q);
      const matchNameEn = item.nameEn.toLowerCase().includes(q);
      const matchDial = item.dialCode.replace('+', '').includes(q.replace('+', ''));
      const matchIso = item.code.toLowerCase().includes(q);
      return matchNameAr || matchNameEn || matchDial || matchIso;
    });
  }, [searchQuery]);

  const handleSelect = async (country: CountryCodeItem) => {
    if (Platform.OS !== 'web') {
      try {
        await Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
      } catch { }
    }
    onSelect(country);
    setSearchQuery('');
    onClose();
  };

  const handleClose = () => {
    setSearchQuery('');
    onClose();
  };

  return (
    <Modal
      visible={visible}
      animationType="slide"
      transparent
      onRequestClose={handleClose}
      statusBarTranslucent
    >
      <TouchableWithoutFeedback onPress={handleClose}>
        <View style={styles.backdrop}>
          <TouchableWithoutFeedback>
            <View
              style={[
                styles.sheetContainer,
                {
                  backgroundColor: isDark ? '#1F2937' : '#FFFFFF',
                  borderColor: isDark ? '#374151' : '#E5E7EB',
                },
              ]}
            >
              {/* Grabber Handle */}
              <View style={styles.handleContainer}>
                <View
                  style={[
                    styles.dragHandle,
                    { backgroundColor: isDark ? '#4B5563' : '#D1D5DB' },
                  ]}
                />
              </View>

              {/* Header */}
              <View style={styles.headerRow}>
                <TouchableOpacity
                  style={[
                    styles.closeBtn,
                    { backgroundColor: isDark ? '#374151' : '#F3F4F6' },
                  ]}
                  onPress={handleClose}
                  hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}
                >
                  <MaterialIcons name="close" size={20} color={colors.text} />
                </TouchableOpacity>

                <View style={styles.headerTextWrap}>
                  <ThemedText style={styles.headerTitle}>اختر الدولة</ThemedText>
                  <ThemedText style={styles.headerSubtitle}>
                    رمز الاتصال الدولي لحساب الواتساب
                  </ThemedText>
                </View>

                <View style={styles.headerPlaceholder} />
              </View>

              {/* Search Bar */}
              <View
                style={[
                  styles.searchBox,
                  {
                    backgroundColor: isDark ? '#111827' : '#F9FAFB',
                    borderColor: isDark ? '#374151' : '#E5E7EB',
                  },
                ]}
              >
                <MaterialIcons name="search" size={22} color={colors.icon} />
                <TextInput
                  style={[styles.searchInput, { color: colors.text }]}
                  placeholder="ابحث بالاسم أو برمز الدولة (مثال: مصر، 20، 966)..."
                  placeholderTextColor={colors.icon}
                  value={searchQuery}
                  onChangeText={setSearchQuery}
                  clearButtonMode="while-editing"
                  autoCorrect={false}
                />
                {searchQuery.length > 0 && (
                  <TouchableOpacity onPress={() => setSearchQuery('')}>
                    <MaterialIcons name="cancel" size={18} color={colors.icon} />
                  </TouchableOpacity>
                )}
              </View>

              {/* Quick Select Frequent Countries Chips */}
              {!searchQuery && (
                <View style={styles.frequentSection}>
                  <ThemedText style={styles.frequentTitle}>الأكثر استخداماً:</ThemedText>
                  <View style={styles.chipsWrap}>
                    {FREQUENT_COUNTRIES.map((c) => {
                      const isSelected = c.code === selectedCountry.code;
                      return (
                        <TouchableOpacity
                          key={c.code}
                          style={[
                            styles.chip,
                            {
                              backgroundColor: isSelected
                                ? silaTheme.primary
                                : isDark
                                  ? '#111827'
                                  : '#F3F4F6',
                              borderColor: isSelected
                                ? silaTheme.primary
                                : isDark
                                  ? '#374151'
                                  : '#E5E7EB',
                            },
                          ]}
                          onPress={() => handleSelect(c)}
                        >
                          <ThemedText style={styles.chipFlag}>{c.flag}</ThemedText>
                          <ThemedText
                            style={[
                              styles.chipText,
                              {
                                color: isSelected ? '#FFFFFF' : colors.text,
                              },
                            ]}
                          >
                            {c.nameAr}
                          </ThemedText>
                        </TouchableOpacity>
                      );
                    })}
                  </View>
                </View>
              )}

              {/* Countries List */}
              <FlatList
                data={filteredCountries}
                keyExtractor={(item) => item.code}
                showsVerticalScrollIndicator={false}
                keyboardShouldPersistTaps="handled"
                contentContainerStyle={styles.listContent}
                renderItem={({ item }) => {
                  const isSelected = item.code === selectedCountry.code;
                  return (
                    <TouchableOpacity
                      style={[
                        styles.countryRow,
                        isSelected && {
                          backgroundColor: isDark
                            ? 'rgba(55, 160, 92, 0.12)'
                            : 'rgba(55, 160, 92, 0.08)',
                        },
                      ]}
                      onPress={() => handleSelect(item)}
                    >
                      <View style={styles.countryInfoRow}>
                        <ThemedText style={styles.flagEmoji}>{item.flag}</ThemedText>
                        <View style={styles.nameWrap}>
                          <ThemedText
                            style={[
                              styles.countryNameAr,
                              isSelected && {
                                color: silaTheme.primary,
                                fontFamily: 'Tajawal-Bold',
                              },
                            ]}
                          >
                            {item.nameAr}
                          </ThemedText>
                          <ThemedText style={styles.countryNameEn} numberOfLines={1}>
                            {item.nameEn}
                          </ThemedText>
                        </View>
                      </View>

                      <View style={styles.codeWrap}>
                        <View
                          style={[
                            styles.dialCodeBadge,
                            {
                              backgroundColor: isSelected
                                ? silaTheme.primary
                                : isDark
                                  ? '#374151'
                                  : '#E5E7EB',
                            },
                          ]}
                        >
                          <ThemedText
                            style={[
                              styles.dialCodeText,
                              { color: isSelected ? '#FFFFFF' : colors.text },
                            ]}
                          >
                            {item.dialCode}
                          </ThemedText>
                        </View>

                        {isSelected && (
                          <MaterialIcons
                            name="check"
                            size={20}
                            color={silaTheme.primary}
                            style={styles.checkIcon}
                          />
                        )}
                      </View>
                    </TouchableOpacity>
                  );
                }}
                ListEmptyComponent={
                  <View style={styles.emptyContainer}>
                    <MaterialIcons name="search-off" size={40} color={colors.icon} />
                    <ThemedText style={styles.emptyText}>
                      لم يتم العثور على أي دولة مطابقة
                    </ThemedText>
                  </View>
                }
              />
            </View>
          </TouchableWithoutFeedback>
        </View>
      </TouchableWithoutFeedback>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.55)',
    justifyContent: 'flex-end',
  },
  sheetContainer: {
    maxHeight: '82%',
    minHeight: '55%',
    borderTopLeftRadius: 28,
    borderTopRightRadius: 28,
    borderWidth: 1,
    borderBottomWidth: 0,
    paddingTop: 10,
    paddingBottom: Platform.OS === 'ios' ? 32 : 16,
  },
  handleContainer: {
    alignItems: 'center',
    paddingVertical: 4,
  },
  dragHandle: {
    width: 44,
    height: 5,
    borderRadius: 2.5,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingVertical: 10,
  },
  closeBtn: {
    width: 34,
    height: 34,
    borderRadius: 17,
    justifyContent: 'center',
    alignItems: 'center',
  },
  headerTextWrap: {
    alignItems: 'center',
  },
  headerTitle: {
    fontSize: 17,
    fontFamily: 'Tajawal-Bold',
  },
  headerSubtitle: {
    fontSize: 12,
    fontFamily: 'Tajawal-Regular',
    opacity: 0.65,
    marginTop: 2,
  },
  headerPlaceholder: {
    width: 34,
  },
  searchBox: {
    flexDirection: 'row',
    alignItems: 'center',
    marginHorizontal: 16,
    marginTop: 6,
    marginBottom: 10,
    paddingHorizontal: 12,
    height: 48,
    borderRadius: 14,
    borderWidth: 1,
    gap: 8,
  },
  searchInput: {
    flex: 1,
    fontFamily: 'Tajawal-Medium',
    fontSize: 14,

  },
  frequentSection: {
    paddingHorizontal: 16,
    marginBottom: 8,
  },
  frequentTitle: {
    fontSize: 12,
    fontFamily: 'Tajawal-Bold',
    opacity: 0.65,
    marginBottom: 6,
  },
  chipsWrap: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 6,
  },
  chip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 20,
    borderWidth: 1,
  },
  chipFlag: {
    fontSize: 14,
  },
  chipText: {
    fontSize: 12,
    fontFamily: 'Tajawal-Medium',
  },
  listContent: {
    paddingHorizontal: 16,
    paddingBottom: 20,
  },
  countryRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 12,
    paddingHorizontal: 12,
    borderRadius: 12,
    marginVertical: 2,
  },
  countryInfoRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    flex: 1,
  },
  flagEmoji: {
    fontSize: 24,
  },
  nameWrap: {
    flex: 1,
  },
  countryNameAr: {
    fontSize: 14,
    fontFamily: 'Tajawal-Medium',
  },
  countryNameEn: {
    fontSize: 11,
    fontFamily: 'Tajawal-Regular',
    opacity: 0.6,
    marginTop: 1,
  },
  codeWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  dialCodeBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 8,
  },
  dialCodeText: {
    fontSize: 13,
    fontFamily: 'Tajawal-Bold',
    direction: 'ltr',
  },
  checkIcon: {
    marginLeft: 2,
  },
  emptyContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 40,
    gap: 8,
  },
  emptyText: {
    fontSize: 14,
    fontFamily: 'Tajawal-Medium',
    opacity: 0.7,
  },
});
