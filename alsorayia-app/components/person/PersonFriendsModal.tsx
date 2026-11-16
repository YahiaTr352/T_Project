import type { PersonFriendItem } from '@/lib/api/familyTreeApi';
import { fetchPersonFriends } from '@/lib/api/familyTreeApi';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { Image } from 'expo-image';
import React, { useEffect, useMemo, useState } from 'react';
import {
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
import { useSafeAreaInsets } from 'react-native-safe-area-context';

interface PersonFriendsModalProps {
  visible: boolean;
  onClose: () => void;
  friends?: PersonFriendItem[];
  personId?: number;
  personName?: string;
  colors: any;
  onSelectFriend: (id: number) => void;
}

export const PersonFriendsModal: React.FC<PersonFriendsModalProps> = ({
  visible,
  onClose,
  friends = [],
  personId,
  personName,
  colors,
  onSelectFriend,
}) => {
  const insets = useSafeAreaInsets();
  const { height: windowHeight } = useWindowDimensions();
  const [searchQuery, setSearchQuery] = useState('');
  const [fetchedFriends, setFetchedFriends] = useState<PersonFriendItem[]>([]);
  const [, setLoading] = useState(false);

  useEffect(() => {
    if (visible && personId && (!friends || friends.length === 0)) {
      setLoading(true);
      fetchPersonFriends(personId)
        .then((res) => {
          if (res.success && Array.isArray(res.friends)) {
            setFetchedFriends(res.friends);
          }
        })
        .catch(() => { })
        .finally(() => setLoading(false));
    }
  }, [visible, personId, friends]);

  const allFriends = (friends && friends.length > 0) ? friends : fetchedFriends;

  const filteredFriends = useMemo(() => {
    if (!searchQuery.trim()) return allFriends;
    const query = searchQuery.trim().toLowerCase();
    return allFriends.filter((f) => {
      const name = (f.full_name || `${f.first_name} ${f.last_name || ''}`).toLowerCase();
      const desc = (f.description || '').toLowerCase();
      const story = (f.friendship_story || '').toLowerCase();
      return name.includes(query) || desc.includes(query) || story.includes(query);
    });
  }, [allFriends, searchQuery]);

  const handleSelect = (id: number) => {
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    onClose();
    onSelectFriend(id);
  };

  const handleClose = () => {
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    setSearchQuery('');
    onClose();
  };

  return (
    <Modal
      visible={visible}
      transparent
      animationType="slide"
      onRequestClose={handleClose}
    >
      <View style={styles.overlay}>
        <View
          style={[
            styles.container,
            {
              backgroundColor: colors.card,
              borderColor: colors.border,
              paddingBottom: Math.max(insets.bottom, 16),
              maxHeight: windowHeight * 0.88,
            },
          ]}
        >
          {/* مقبض السحب للأعلى */}
          <View style={styles.dragHandleWrap}>
            <View style={[styles.dragHandle, { backgroundColor: colors.border }]} />
          </View>

          {/* رأس المودال */}
          <View style={styles.header}>
            <TouchableOpacity
              onPress={handleClose}
              style={[styles.closeBtn, { backgroundColor: colors.primary + '15' }]}
              hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}
            >
              <Ionicons name="close" size={20} color={colors.text} />
            </TouchableOpacity>

            <View style={styles.headerTitleWrap}>
              <Text style={[styles.headerTitle, { color: colors.text }]}>
                أصدقاء {personName ? `«${personName}»` : ''}
              </Text>
              <Text style={[styles.headerSubtitle, { color: colors.icon }]}>
                {friends.length} {friends.length === 1 ? 'صديق' : 'أصدقاء'} مسجلين
              </Text>
            </View>

            <View style={{ width: 36 }} />
          </View>

          {/* حقل البحث إذا كان هناك أكثر من 4 أصدقاء */}
          {friends.length > 4 && (
            <View style={[styles.searchBox, { backgroundColor: colors.background, borderColor: colors.border }]}>
              <Ionicons name="search-outline" size={18} color={colors.icon} style={{ marginLeft: 6 }} />
              <TextInput
                style={[styles.searchInput, { color: colors.text }]}
                placeholder="ابحث بالاسم أو وصف الصداقة..."
                placeholderTextColor={colors.icon}
                value={searchQuery}
                onChangeText={setSearchQuery}
                textAlign="right"
              />
              {searchQuery.length > 0 && (
                <TouchableOpacity onPress={() => setSearchQuery('')}>
                  <Ionicons name="close-circle" size={18} color={colors.icon} />
                </TouchableOpacity>
              )}
            </View>
          )}

          {/* قائمة الأصدقاء */}
          <ScrollView
            showsVerticalScrollIndicator={false}
            contentContainerStyle={[
              styles.scrollContent,
              filteredFriends.length === 0 && styles.emptyScrollContent,
            ]}
          >
            {filteredFriends.length === 0 ? (
              <View style={styles.emptyWrap}>
                <Ionicons name="people-outline" size={54} color={colors.icon} style={{ opacity: 0.6 }} />
                <Text style={[styles.emptyText, { color: colors.text }]}>
                  {searchQuery ? 'لا توجد نتائج مطابقة للبحث' : 'لا يوجد أصدقاء مسجلون لهذا العضو'}
                </Text>
              </View>
            ) : (
              filteredFriends.map((friend) => {
                const displayName = friend.full_name || `${friend.first_name} ${friend.last_name || ''}`.trim();
                const isDeceased = !!friend.death_date;
                const isFemale = friend.gender === 'female';

                return (
                  <TouchableOpacity
                    key={friend.id}
                    style={[
                      styles.friendCard,
                      {
                        backgroundColor: colors.background,
                        borderColor: colors.border,
                      },
                    ]}
                    activeOpacity={0.7}
                    onPress={() => handleSelect(friend.id)}
                  >
                    {/* الصورة الرمزية */}
                    <View style={styles.avatarWrap}>
                      {friend.photo_url ? (
                        <Image
                          source={{ uri: friend.photo_url }}
                          style={styles.avatar}
                          contentFit="cover"
                          transition={150}
                        />
                      ) : (
                        <View style={[styles.avatarPlaceholder, { backgroundColor: colors.primary + '18' }]}>
                          <Ionicons
                            name={isFemale ? 'woman' : 'man'}
                            size={28}
                            color={colors.primary}
                          />
                        </View>
                      )}
                      {isDeceased && (
                        <View style={styles.deceasedBadge}>
                          <Text style={styles.deceasedText}>{isFemale ? 'رحمها الله' : 'رحمه الله'}</Text>
                        </View>
                      )}
                    </View>

                    {/* معلومات الصديق */}
                    <View style={styles.infoWrap}>
                      <View style={styles.nameRow}>
                        <Text style={[styles.friendName, { color: colors.text }]} numberOfLines={1}>
                          {displayName}
                        </Text>
                      </View>

                      {friend.description ? (
                        <View style={[styles.descPill, { backgroundColor: colors.primary + '12' }]}>
                          <Ionicons name="heart-outline" size={12} color={colors.primary} style={{ marginLeft: 4 }} />
                          <Text style={[styles.descText, { color: colors.primary }]} numberOfLines={1}>
                            {friend.description}
                          </Text>
                        </View>
                      ) : null}

                      {friend.friendship_story ? (
                        <Text style={[styles.storySnippet, { color: colors.icon }]} numberOfLines={2}>
                          {friend.friendship_story}
                        </Text>
                      ) : null}
                    </View>

                    {/* مؤشر الانتقال */}
                    <View style={styles.arrowWrap}>
                      <Ionicons
                        name={Platform.OS === 'ios' ? 'chevron-back' : 'arrow-back'}
                        size={18}
                        color={colors.primary}
                      />
                    </View>
                  </TouchableOpacity>
                );
              })
            )}
          </ScrollView>
        </View>
      </View>
    </Modal>
  );
};

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.55)',
    justifyContent: 'flex-end',
  },
  container: {
    borderTopLeftRadius: 28,
    borderTopRightRadius: 28,
    borderTopWidth: 1,
    paddingTop: 10,
    paddingHorizontal: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: -6 },
    shadowOpacity: 0.15,
    shadowRadius: 16,
    elevation: 20,
  },
  dragHandleWrap: {
    alignItems: 'center',
    paddingVertical: 6,
  },
  dragHandle: {
    width: 44,
    height: 4.5,
    borderRadius: 3,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: 'rgba(150,150,150,0.15)',
  },
  closeBtn: {
    width: 36,
    height: 36,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerTitleWrap: {
    alignItems: 'center',
    flex: 1,
  },
  headerTitle: {
    fontSize: 16,
    fontFamily: 'Tajawal-Bold',
    textAlign: 'center',
  },
  headerSubtitle: {
    fontSize: 12,
    fontFamily: 'Tajawal-Regular',
    marginTop: 2,
  },
  searchBox: {
    flexDirection: 'row',
    alignItems: 'center',
    borderRadius: 12,
    borderWidth: 1,
    paddingHorizontal: 12,
    paddingVertical: Platform.OS === 'ios' ? 8 : 4,
    marginTop: 12,
    marginBottom: 4,
  },
  searchInput: {
    flex: 1,
    fontSize: 13,
    fontFamily: 'Tajawal-Regular',
    paddingHorizontal: 8,
  },
  scrollContent: {
    paddingVertical: 12,
    gap: 10,
  },
  emptyScrollContent: {
    flexGrow: 1,
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 220,
  },
  emptyWrap: {
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
  },
  emptyText: {
    fontSize: 14,
    fontFamily: 'Tajawal-Medium',
    textAlign: 'center',
  },
  friendCard: {
    flexDirection: 'row',
    alignItems: 'center',
    borderRadius: 16,
    borderWidth: 1,
    padding: 12,
    gap: 12,
  },
  avatarWrap: {
    position: 'relative',
  },
  avatar: {
    width: 54,
    height: 54,
    borderRadius: 27,
  },
  avatarPlaceholder: {
    width: 54,
    height: 54,
    borderRadius: 27,
    alignItems: 'center',
    justifyContent: 'center',
  },
  deceasedBadge: {
    position: 'absolute',
    bottom: -4,
    left: -4,
    right: -4,
    backgroundColor: '#333',
    borderRadius: 8,
    paddingVertical: 1,
    alignItems: 'center',
  },
  deceasedText: {
    color: '#fff',
    fontSize: 8,
    fontFamily: 'Tajawal-Bold',
  },
  infoWrap: {
    flex: 1,
    alignItems: 'flex-end',
    gap: 4,
  },
  nameRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  friendName: {
    fontSize: 14,
    fontFamily: 'Tajawal-Bold',

  },
  descPill: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 8,
    paddingVertical: 2.5,
    borderRadius: 8,
  },
  descText: {
    fontSize: 11,
    fontFamily: 'Tajawal-Medium',
  },
  storySnippet: {
    fontSize: 11,
    fontFamily: 'Tajawal-Regular',

    lineHeight: 16,
  },
  arrowWrap: {
    width: 28,
    height: 28,
    borderRadius: 14,
    backgroundColor: 'rgba(55,160,92,0.1)',
    alignItems: 'center',
    justifyContent: 'center',
  },
});
