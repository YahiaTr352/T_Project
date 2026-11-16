import React from 'react';
import { View, ScrollView, StyleSheet, Platform, useColorScheme } from 'react-native';
import { useRouter } from 'expo-router';
import { ThemedView } from '@/components/themed-view';
import { ThemedText } from '@/components/themed-text';
import HeaderBar from '@/components/headerBar';
import { Colors } from '@/constants/theme';

export default function PrivacyScreen() {
  const router = useRouter();
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];

  return (
    <ThemedView style={styles.container}>
      <View style={{ paddingTop: Platform.OS === 'ios' ? 40 : 10, paddingHorizontal: 16 }}>
        <HeaderBar title="سياسة الخصوصية" showBack onBackPress={() => router.back()} />
      </View>
      <ScrollView contentContainerStyle={styles.content}>
        <View style={[styles.card, { backgroundColor: colorScheme === 'dark' ? colors.card : '#FFFFFF', borderColor: colors.border }]}>
          <ThemedText style={styles.title}>حماية بياناتك</ThemedText>
          <ThemedText style={styles.text}>
            نحن في تطبيق عائلة السريّع نقدر خصوصيتك ونلتزم بحماية بياناتك الشخصية:
            {'\n\n'}• لا نقوم ببيع بياناتك لأي أطراف خارجية.
            {'\n\n'}• تُستخدم البيانات المجمعة فقط لتحسين تجربتك داخل التطبيق وإبقائك على تواصل مع مستجدات العائلة.
            {'\n\n'}• يمكنك في أي وقت طلب مسح بياناتك أو إيقاف الإشعارات.
          </ThemedText>
        </View>
      </ScrollView>
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  content: { padding: 20, paddingBottom: 60 },
  card: {
    borderRadius: 16,
    borderWidth: 1,
    padding: 20,
  },
  title: { fontFamily: 'Tajawal-Bold', fontSize: 18, marginBottom: 12 },
  text: { fontFamily: 'Tajawal-Regular', fontSize: 15, lineHeight: 28 },
});
