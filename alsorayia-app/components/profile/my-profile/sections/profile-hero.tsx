import type { Dispatch, SetStateAction } from 'react';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { useRouter } from 'expo-router';
import type { Href } from 'expo-router';
import { ActivityIndicator, Image, Pressable, Text, TouchableOpacity, View } from 'react-native';
import { useColorScheme } from '@/hooks/use-color-scheme';
import type { MyPerson, MyProfilePayload } from '@/lib/api/myProfile';
import type { ProfileTabKey } from '@/components/profile/ProfileSectionTabs';
import { styles } from '../styles';

interface ProfileHeroProps {
  data: MyProfilePayload;
  person: MyPerson;
  saving: boolean;
  photoUri: string | null;
  photoBase64: string | null;
  authBadge: string;
  setActiveTab: Dispatch<SetStateAction<ProfileTabKey>>;
  pickPhoto: () => unknown;
}

export function ProfileHero({
  data,
  person,
  saving,
  photoUri,
  photoBase64,
  authBadge,
  setActiveTab,
  pickPhoto,
}: ProfileHeroProps) {
  const router = useRouter();
  const isDark = useColorScheme() === 'dark';
  return (
          <LinearGradient
            colors={isDark ? ['#065F46', '#047857', '#022C22'] : ['#059669', '#047857', '#065F46']}
            start={{ x: 0, y: 0 }}
            end={{ x: 1, y: 1 }}
            style={styles.heroCard}>

            <View style={styles.heroTopRow}>
              {/* Profile Avatar with Camera Upload Badge */}
              <TouchableOpacity
                onPress={() => void pickPhoto()}
                disabled={saving}
                activeOpacity={0.85}
                style={styles.avatarWrapContainer}>
                {photoUri || person.avatar ? (
                  <Image source={{ uri: photoUri ?? person.avatar ?? undefined }} style={styles.heroAvatar} />
                ) : (
                  <View style={styles.heroAvatarFallback}>
                    <Ionicons name="person" size={42} color="#047857" />
                  </View>
                )}
                <View style={styles.cameraBadge}>
                  {saving && (photoUri || photoBase64) ? (
                    <ActivityIndicator size="small" color="#FFF" />
                  ) : (
                    <Ionicons name="camera" size={14} color="#FFF" />
                  )}
                </View>
              </TouchableOpacity>

              {/* Identity Info */}
              <View style={{ flex: 1 }}>
                <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8 }}>
                  <View style={styles.authBadgePill}>
                    <Text style={styles.authBadgeText}>{authBadge}</Text>
                  </View>
                  {/* <TouchableOpacity
                    style={styles.heroPrivacyPillBtn}
                    onPress={() => setShowPrivacyModal(true)}
                    activeOpacity={0.85}>
                    <Ionicons name="shield-checkmark" size={13} color="#FFFFFF" />
                    <Text style={styles.heroPrivacyPillText}>الخصوصية</Text>
                  </TouchableOpacity> */}
                </View>
                <Text style={styles.heroName} numberOfLines={1}>{person.full_name}</Text>
                <Text style={styles.heroOccupation} numberOfLines={1}>
                  {person.occupation || 'عضو في عائلة السريّع'}
                </Text>

                <TouchableOpacity
                  style={styles.viewPublicProfileLink}
                  onPress={() => router.push(`/(app)/person/${person.id}` as Href)}>
                  <Ionicons name="eye-outline" size={14} color="#A7F3D0" />
                  <Text style={styles.viewPublicProfileText}>عرض الملف العام</Text>
                  <Ionicons name="chevron-back" size={12} color="#A7F3D0" />
                </TouchableOpacity>
              </View>
            </View>

            {/* Quick Interactive Profile Stats Strip */}
            <View style={styles.statsContainer}>
              <Pressable onPress={() => setActiveTab('children')} style={styles.statBox}>
                <Text style={styles.statNumber}>{data.children.length}</Text>
                <Text style={styles.statLabel}>الأبناء</Text>
              </Pressable>
              <View style={styles.statDivider} />
              <Pressable onPress={() => setActiveTab('marriages')} style={styles.statBox}>
                <Text style={styles.statNumber}>{data.marriages.length}</Text>
                <Text style={styles.statLabel}>الزواج</Text>
              </Pressable>
              <View style={styles.statDivider} />
              <Pressable onPress={() => setActiveTab('contacts')} style={styles.statBox}>
                <Text style={styles.statNumber}>{data.contacts.length}</Text>
                <Text style={styles.statLabel}>التواصل</Text>
              </Pressable>
              <View style={styles.statDivider} />
              <Pressable onPress={() => setActiveTab('locations')} style={styles.statBox}>
                <Text style={styles.statNumber}>{data.locations.length}</Text>
                <Text style={styles.statLabel}>الأماكن</Text>
              </Pressable>
            </View>
          </LinearGradient>
  );
}
