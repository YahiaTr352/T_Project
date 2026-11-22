import { AnimatedBubbles } from '@/components/animated-bubbles';
import HeaderBar from '@/components/headerBar';
import { CreativeSpace } from '@/components/invitations/CreativeSpace';
import { GroupsTab } from '@/components/invitations/GroupsTab';
import { InvitationComposer } from '@/components/invitations/InvitationComposer';
import { InvitationTabBar, type InvitationTabKey } from '@/components/invitations/InvitationTabBar';
import { LogsTab } from '@/components/invitations/LogsTab';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { Colors, type ThemeColors } from '@/constants/theme';
import { useAuth } from '@/contexts/auth-context';
import { useInvitationGroups } from '@/hooks/use-invitation-groups';
import { useInvitationLogs } from '@/hooks/use-invitation-logs';
import { useInvitationTemplates } from '@/hooks/use-invitation-templates';
import type { InvitationLogDirection } from '@/lib/api/invitations';
import {
  getInvitationEligibility,
  missingFieldsLabel,
} from '@/lib/utils/invitationEligibility';
import { LinearGradient } from 'expo-linear-gradient';
import { useRouter } from 'expo-router';
import React, { useState } from 'react';
import {
  Keyboard,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  StyleSheet,
  View,
  useColorScheme,
} from 'react-native';

export default function InvitesScreen() {
  const router = useRouter();
  const colorScheme = useColorScheme();
  const colors: ThemeColors = Colors[colorScheme ?? 'light'];
  const isDark = colorScheme === 'dark';
  const { hasIdentity, user, lock } = useAuth();
  const [tab, setTab] = useState<InvitationTabKey>('compose');
  const [creativeOpened, setCreativeOpened] = useState(false);
  const [logDirection, setLogDirection] = useState<InvitationLogDirection>('outgoing');

  // الأهلية المحلية: هوية (وجه/واتساب) + عمر 20+ + بيانات أساسية (الاسم + الميلاد)
  // دخول رمز الموقع (112/passcode) بلا user.id → reason=identity → بوابة التوثيق
  const eligibility = getInvitationEligibility(hasIdentity, user);

  const templates = useInvitationTemplates(hasIdentity);
  const groups = useInvitationGroups(hasIdentity);
  const logs = useInvitationLogs(hasIdentity);
  const incomingLogs = useInvitationLogs(hasIdentity && tab === 'logs' && logDirection === 'incoming', 'incoming');
  const activeLogs = logDirection === 'incoming' ? incomingLogs : logs;

  const serverIdentityBlocked =
    templates.identityRequired || groups.identityRequired || logs.identityRequired;
  const serverIncompleteBlocked =
    (templates as any).profileIncomplete ||
    (groups as any).profileIncomplete ||
    (logs as any).profileIncomplete;
  const serverUnderageBlocked =
    (templates as any).underageBlocked ||
    (groups as any).underageBlocked ||
    (logs as any).underageBlocked;

  const serverBlockMessage =
    templates.error || groups.error || logs.error || null;

  const showUnder20 =
    eligibility.reason === 'under20' || serverUnderageBlocked;
  const showIdentity =
    !showUnder20 &&
    (eligibility.reason === 'identity' || serverIdentityBlocked);
  const showIncomplete =
    !showUnder20 &&
    !showIdentity &&
    (eligibility.reason === 'incomplete' || serverIncompleteBlocked);

  const missingLabel = missingFieldsLabel(
    eligibility.reason === 'incomplete' ? eligibility.missing : []
  );

  const handleSent = () => {
    void logs.refresh();
    void groups.refresh();
    setLogDirection('outgoing');
    setTab('logs');
  };

  const selectTab = (next: InvitationTabKey) => {
    if (next === tab) return;
    Keyboard.dismiss();
    if (next === 'creative') setCreativeOpened(true);
    setTab(next);
  };

  const composing = tab === 'compose';
  const showingCreative = tab === 'creative';

  return (
    <ThemedView style={styles.container}>
      <AnimatedBubbles bubbleCount={7} minSize={40} maxSize={110} />
      <HeaderBar title="الدعوات" showBack onBackPress={() => router.back()} />

      {showUnder20 ? (
        <View style={styles.centered}>
          <LinearGradient
            colors={isDark ? ['#1E293B', '#0F172A'] : ['#FEF3C7', '#FDE68A']}
            style={styles.gateIconWrap}>
            <IconSymbol name="exclamationmark.triangle.fill" size={44} color="#D97706" />
          </LinearGradient>
          <ThemedText style={styles.gateTitle}>غير متاح لعمرك</ThemedText>
          <ThemedText style={[styles.gateText, { color: colors.icon }]}>
            ميزة إرسال الدعوات متاحة فقط لمن بلغ 20 سنة فأكثر.
          </ThemedText>
        </View>
      ) : showIdentity ? (
        <View style={styles.centered}>
          <LinearGradient
            colors={isDark ? ['#1E293B', '#0F172A'] : ['#ECFDF5', '#D1FAE5']}
            style={styles.gateIconWrap}>
            <IconSymbol name="lock.shield.fill" size={44} color={colors.primary} />
          </LinearGradient>
          <ThemedText style={styles.gateTitle}>توثيق الهوية مطلوب</ThemedText>
          <ThemedText style={[styles.gateText, { color: colors.icon }]}>
            مجموعات الدعوات والإرسال متاحة بعد تسجيل الدخول ببصمة الوجه أو واتساب.
            {'\n'}الدخول برمز الموقع فقط لا يتيح إرسال الدعوات.
          </ThemedText>
          <Pressable
            onPress={() => void lock()}
            style={[styles.primaryBtn, { backgroundColor: colors.primary }]}>
            <IconSymbol name="lock.fill" size={18} color="#FFFFFF" />
            <ThemedText style={styles.primaryBtnText}>توثيق الهوية الآن</ThemedText>
          </Pressable>
        </View>
      ) : showIncomplete ? (
        <View style={styles.centered}>
          <LinearGradient
            colors={isDark ? ['#1E293B', '#0F172A'] : ['#FEF3C7', '#FDE68A']}
            style={styles.gateIconWrap}>
            <IconSymbol name="person.crop.circle.badge.exclamationmark" size={44} color="#D97706" />
          </LinearGradient>
          <ThemedText style={styles.gateTitle}>أكمل بياناتك الأساسية أولاً</ThemedText>
          <ThemedText style={[styles.gateText, { color: colors.icon }]}>
            {missingLabel
              ? `لا يمكن إرسال الدعوات لأن بياناتك الأساسية ناقصة: ${missingLabel}.`
              : serverBlockMessage ?? 'بياناتك الأساسية غير مكتملة.'}
            {'\n'}المطلوب فقط: الاسم + تاريخ الميلاد (البيانات الفرعية مثل السيرة والوظيفة ليست شرطاً).
          </ThemedText>
          <Pressable
            onPress={() => router.push('/(app)/my-profile' as any)}
            style={[styles.primaryBtn, { backgroundColor: colors.primary }]}>
            <IconSymbol name="person.fill" size={18} color="#FFFFFF" />
            <ThemedText style={styles.primaryBtnText}>إكمال البيانات الآن</ThemedText>
          </Pressable>
        </View>
      ) : (
        <View style={styles.body}>
          <InvitationTabBar
            active={tab}
            onChange={selectTab}
            groupsCount={groups.groups.length}
          />

          {composing || showingCreative ? (
            <KeyboardAvoidingView
              style={[styles.flex, !composing && styles.hidden]}
              enabled={composing}
              behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
              keyboardVerticalOffset={Platform.OS === 'ios' ? 100 : 0}
            >
              <InvitationComposer
                templates={templates.templates}
                templatesLoading={templates.loading}
                groups={groups.groups}
                colors={colors}
                isDark={isDark}
                onSent={handleSent}
              />
            </KeyboardAvoidingView>
          ) : null}

          {creativeOpened ? (
            <KeyboardAvoidingView
              style={[styles.flex, !showingCreative && styles.hidden]}
              enabled={showingCreative}
              behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
              keyboardVerticalOffset={Platform.OS === 'ios' ? 100 : 0}
            >
              <CreativeSpace active={showingCreative} types={templates} />
            </KeyboardAvoidingView>
          ) : null}

          {tab === 'groups' ? (
            <GroupsTab
              groups={groups.groups}
              loading={groups.loading}
              refreshing={groups.refreshing}
              saving={groups.saving}
              error={groups.error}
              onRefresh={() => void groups.refresh()}
              onCreate={groups.createGroup}
              onUpdate={groups.updateGroup}
              onDelete={groups.deleteGroup}
              onDetach={groups.detachPerson}
              colors={colors}
              isDark={isDark}
            />
          ) : null}

          {tab === 'logs' ? (
            <>
              <View style={styles.logTabs}>
                {(['outgoing', 'incoming'] as const).map((direction) => {
                  const selected = direction === logDirection;
                  return (
                    <Pressable
                      key={direction}
                      accessibilityRole="tab"
                      accessibilityState={{ selected }}
                      onPress={() => setLogDirection(direction)}
                      style={[
                        styles.logTab,
                        {
                          backgroundColor: selected ? colors.primary : colors.card,
                          borderColor: selected ? colors.primary : colors.border,
                        },
                      ]}>
                      <ThemedText style={[styles.logTabLabel, { color: selected ? '#FFFFFF' : colors.text }]}>
                        {direction === 'outgoing' ? 'الصادر' : 'الوارد'}
                      </ThemedText>
                    </Pressable>
                  );
                })}
              </View>
              <LogsTab
                key={logDirection}
                incoming={logDirection === 'incoming'}
                items={activeLogs.items}
                loading={activeLogs.loading}
                refreshing={activeLogs.refreshing}
                loadingMore={activeLogs.loadingMore}
                error={activeLogs.error}
                hasMore={activeLogs.hasMore}
                onRefresh={activeLogs.refresh}
                onLoadMore={activeLogs.loadMore}
                onOpenDetail={activeLogs.fetchDetail}
                onItemUpdated={activeLogs.updateItem}
                colors={colors}
                isDark={isDark}
              />
            </>
          ) : null}
        </View>
      )}
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  logTabs: {
    flexDirection: 'row',
    gap: 8,
    marginTop: 4,
    marginBottom: 12,
  },
  logTab: {
    flex: 1,
    alignItems: 'center',
    paddingVertical: 10,
    borderRadius: 14,
    borderWidth: 1,
  },
  logTabLabel: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 14,
  },
  container: {
    flex: 1,
    direction: 'rtl',
  },
  body: {
    flex: 1,
    paddingHorizontal: 16,
  },
  flex: {
    flex: 1,
  },
  hidden: {
    display: 'none',
  },
  scrollContent: {
    paddingBottom: 40,
  },
  centered: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 32,
  },
  gateIconWrap: {
    width: 90,
    height: 90,
    borderRadius: 45,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 16,
  },
  gateTitle: {
    fontFamily: 'Tajawal-Bold',
    fontSize: 20,
    textAlign: 'center',
    marginBottom: 8,
  },
  gateText: {
    fontFamily: 'Tajawal-Regular',
    fontSize: 14,
    textAlign: 'center',
    lineHeight: 22,
    marginBottom: 20,
  },
  primaryBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingHorizontal: 22,
    paddingVertical: 12,
    borderRadius: 14,
  },
  primaryBtnText: {
    color: '#FFFFFF',
    fontFamily: 'Tajawal-Bold',
    fontSize: 15,
  },
});
