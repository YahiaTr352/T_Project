import { Stack } from 'expo-router';
import React from 'react';

export default function AppLayout() {
  return (
    <Stack screenOptions={{ headerShown: false }}>
      <Stack.Screen name="notifications" />
      <Stack.Screen name="search" />
      <Stack.Screen name="person/[id]" />
      <Stack.Screen name="settings" />
      <Stack.Screen name="my-profile" />
      <Stack.Screen name="help" />
      <Stack.Screen name="contact" />
      <Stack.Screen name="terms" />
      <Stack.Screen name="privacy" />
      <Stack.Screen name="important-link/[id]" />
      <Stack.Screen name="ambitious-students" />
      <Stack.Screen name="breastfeeding" />
      <Stack.Screen name="reports" />
      <Stack.Screen name="invites" />
      <Stack.Screen name="program/[id]" />
      <Stack.Screen name="quran-competitions/[id]" />
      <Stack.Screen name="quran-competitions/category/[id]" />
      <Stack.Screen name="store" />
      <Stack.Screen name="articles" />
      <Stack.Screen name="article/[id]" />
      <Stack.Screen name="walking-chat/[id]" />
      <Stack.Screen name="quiz-chat/[id]" />
      <Stack.Screen name="walking-trek" />
    </Stack>
  );
}
