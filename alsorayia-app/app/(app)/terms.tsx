import React from 'react';
import { View, ScrollView, StyleSheet, Platform, useColorScheme } from 'react-native';
import { useRouter } from 'expo-router';
import { ThemedView } from '@/components/themed-view';
import { ThemedText } from '@/components/themed-text';
import HeaderBar from '@/components/headerBar';
import { Colors } from '@/constants/theme';

export default function TermsScreen() {
  const router = useRouter();
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];

  return (
    <ThemedView style={styles.container}>
      <View style={{ paddingTop: Platform.OS === 'ios' ? 40 : 10, paddingHorizontal: 16 }}>
        <HeaderBar title="الشروط والأحكام" showBack onBackPress={() => router.back()} />
      </View>
      <ScrollView contentContainerStyle={styles.content}>
        <View style={[styles.card, { backgroundColor: colorScheme === 'dark' ? colors.card : '#FFFFFF', borderColor: colors.border }]}>
          <ThemedText style={styles.title}>شروط استخدام التطبيق</ThemedText>
          <ThemedText style={styles.text}>
            1. القبول بالشروط: بتسجيلك في التطبيق فإنك توافق على جميع الشروط والأحكام الموضحة هنا.
            {'\n\n'}2. الاستخدام العادل: يُمنع استخدام التطبيق لأي أغراض تجارية غير مصرح بها.
            {'\n\n'}3. النشر والمحتوى: أي محتوى تقوم بمشاركته يجب أن يتوافق مع الآداب العامة وقيم عائلة السريّع.
            {'\n\n'}4. التعديل: نحتفظ بالحق في تعديل الشروط في أي وقت مع إشعار المستخدمين بذلك.
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
