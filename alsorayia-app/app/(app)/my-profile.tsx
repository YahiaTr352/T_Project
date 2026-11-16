import HeaderBar from '@/components/headerBar';
import { ProfilePrivacyModal } from '@/components/profile/ProfilePrivacyModal';
import { ProfileSectionTabs, type ProfileTabKey } from '@/components/profile/ProfileSectionTabs';
import { ThemedView } from '@/components/themed-view';
import { CustomAlert } from '@/components/ui/AlertModal';
import { Colors } from '@/constants/theme';
import { useAuth } from '@/contexts/auth-context';
import { useColorScheme } from '@/hooks/use-color-scheme';
import { useMyProfile } from '@/hooks/use-my-profile';
import type { ContactType, PrivacyVisibility, ProfilePrivacySettings, StoreMarriageInput } from '@/lib/api/myProfile';
import React, { useMemo, useState } from 'react';
import * as ImagePicker from 'expo-image-picker';
import { useRouter } from 'expo-router';
import {
  ActivityIndicator,
  Platform,
  ScrollView,
  Text,
  View,
} from 'react-native';
import { styles } from '@/components/profile/my-profile/styles';
import { AuthGate, ProfileStatusGate } from '@/components/profile/my-profile/sections/gates';
import { ProfileHero } from '@/components/profile/my-profile/sections/profile-hero';
import { ParentsCard, PrivacyBanner } from '@/components/profile/my-profile/sections/parents-privacy';
import { BasicTab } from '@/components/profile/my-profile/sections/basic-tab';
import { ContactsTab } from '@/components/profile/my-profile/sections/contacts-tab';
import { ChildrenTab } from '@/components/profile/my-profile/sections/children-tab';
import { MarriagesTab } from '@/components/profile/my-profile/sections/marriages-tab';
import { LocationsTab } from '@/components/profile/my-profile/sections/locations-tab';

export default function MyProfileScreen() {
  const router = useRouter();
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const isDark = colorScheme === 'dark';
  const { hasIdentity, lock } = useAuth();
  const { status, data, errorMessage, saving, reload, updateProfile, addContact, removeContact, addChild, addMarriage, patchMarriage, addLocation, removeLocation, updatePrivacy } = useMyProfile();

  const [activeTab, setActiveTab] = useState<ProfileTabKey>('basic');
  const [showPrivacyModal, setShowPrivacyModal] = useState(false);
  const [showDeathFields, setShowDeathFields] = useState(false);

  const person = data?.person;

  const [basicForm, setBasicForm] = useState({
    first_name: '',
    last_name: '',
    birth_date: '',
    birth_place: '',
    occupation: '',
    biography: '',
    death_date: '',
    death_place: '',
    cemetery: '',
    cemetery_location: '',
    grave_number: '',
  });
  const [photoUri, setPhotoUri] = useState<string | null>(null);
  const [photoBase64, setPhotoBase64] = useState<string | null>(null);

  const [contactForm, setContactForm] = useState({ type: 'whatsapp' as ContactType, value: '', label: '' });
  const [childForm, setChildForm] = useState({
    first_name: '',
    gender: 'male' as 'male' | 'female',
    birth_date: '',
    parentChoiceId: 0,
  });
  const [marriageForm, setMarriageForm] = useState<{
    spouseType: 'existing' | 'outside_family' | 'inside_family';
    spouseId: number;
    firstName: string;
    lastName: string;
    parentId: number;
    birthDate: string;
    photoUri: string | null;
    married_at: string;
    searchQuery: string;
  }>({
    spouseType: 'existing',
    spouseId: 0,
    firstName: '',
    lastName: '',
    parentId: 0,
    birthDate: '',
    photoUri: null,
    married_at: '',
    searchQuery: '',
  });
  const [marriageEdits, setMarriageEdits] = useState<Record<number, { married_at: string; divorced_at: string; is_divorced: boolean }>>({});
  const [locationForm, setLocationForm] = useState({ location_id: 0, label: '', url: '' });
  const [privacyForm, setPrivacyForm] = useState<ProfilePrivacySettings>({
    photo: 'public',
    birth_date: 'family',
    location: 'public',
    biography: 'public',
    occupation: 'public',
    contacts: 'family',
  });
  const [contactVisibilities, setContactVisibilities] = useState<Record<number, PrivacyVisibility>>({});

  React.useEffect(() => {
    if (!person) return;
    setBasicForm({
      first_name: person.first_name ?? '',
      last_name: person.last_name ?? '',
      birth_date: person.birth_date ?? '',
      birth_place: person.birth_place ?? '',
      occupation: person.occupation ?? '',
      biography: person.biography ?? '',
      death_date: person.death_date ?? '',
      death_place: person.death_place ?? '',
      cemetery: person.cemetery ?? '',
      cemetery_location: person.cemetery_location ?? '',
      grave_number: person.grave_number ?? '',
    });
    setShowDeathFields(Boolean(person.death_date || person.death_place));
    if (data?.locations_catalog?.[0] && locationForm.location_id === 0) {
      setLocationForm((f) => ({ ...f, location_id: data.locations_catalog[0].id }));
    }
    // لا يتم التحديد التلقائي للزوج/الزوجة في نموذج إضافة الزواج لمنع الاختيار الخاطئ
    const firstWife = data?.wife_candidates?.[0];
    const firstHusband = data?.husband_candidates?.[0];
    if (person.gender === 'male' && firstWife && childForm.parentChoiceId === 0) {
      setChildForm((f) => ({ ...f, parentChoiceId: firstWife.id }));
    } else if (person.gender === 'female' && firstHusband && childForm.parentChoiceId === 0) {
      setChildForm((f) => ({ ...f, parentChoiceId: firstHusband.id }));
    }
    if (data?.marriages) {
      const edits: typeof marriageEdits = {};
      for (const m of data.marriages) {
        edits[m.id] = {
          married_at: m.married_at ?? '',
          divorced_at: m.divorced_at ?? '',
          is_divorced: m.is_divorced,
        };
      }
      setMarriageEdits(edits);
    }
    if (data?.privacy_settings) {
      setPrivacyForm({
        photo: data.privacy_settings.photo ?? 'public',
        birth_date: data.privacy_settings.birth_date ?? 'family',
        location: data.privacy_settings.location ?? 'public',
        biography: data.privacy_settings.biography ?? 'public',
        occupation: data.privacy_settings.occupation ?? 'public',
        contacts: data.privacy_settings.contacts ?? 'family',
      });
    }
    if (data?.contacts) {
      const cVis: Record<number, PrivacyVisibility> = {};
      for (const c of data.contacts) {
        cVis[c.id] = c.visibility ?? 'family';
      }
      setContactVisibilities(cVis);
    }
  }, [person, data]);

  const authBadge = useMemo(() => {
    if (data?.auth_method === 'face') return 'بصمة الوجه 🟢';
    if (data?.auth_method === 'whatsapp') return 'واتساب 🟢';
    return 'عضو موثّق 🟢';
  }, [data?.auth_method]);

  const pickPhoto = async () => {
    const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!perm.granted) {
      CustomAlert.alert('الإذن مطلوب', 'يرجى السماح بالوصول للمعرض لاختيار صورتك الشخصية.');
      return;
    }
    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images'],
      allowsEditing: true,
      aspect: [1, 1],
      quality: 0.7,
      base64: true,
    });
    if (!result.canceled && result.assets[0]) {
      const asset = result.assets[0];
      const selectedUri = asset.uri;
      const selectedBase64 = asset.base64 ? `data:image/jpeg;base64,${asset.base64}` : null;
      setPhotoUri(selectedUri);
      setPhotoBase64(selectedBase64);
      CustomAlert.alert(
        'تم اختيار الصورة الشخصية',
        'هل ترغب في حفظ وتحديث صورتك الشخصية في حسابك الآن؟',
        [
          { text: 'لاحقاً مع البيانات', style: 'cancel' },
          {
            text: 'حفظ وتحديث الآن',
            onPress: () => {
              void handleSaveBasic(selectedUri, selectedBase64);
            },
          },
        ]
      );
    }
  };

  const handleSaveBasic = async (overridePhotoUri?: string, overridePhotoBase64?: string | null) => {
    const targetPhoto = overridePhotoUri !== undefined ? overridePhotoUri : photoUri;
    const targetBase64 = overridePhotoBase64 !== undefined ? overridePhotoBase64 : photoBase64;
    const res = await updateProfile({
      birth_date: basicForm.birth_date || null,
      birth_place: basicForm.birth_place || null,
      occupation: basicForm.occupation || null,
      biography: basicForm.biography || null,
      death_date: showDeathFields ? basicForm.death_date || null : null,
      death_place: showDeathFields ? basicForm.death_place || null : null,
      cemetery: showDeathFields ? basicForm.cemetery || null : null,
      cemetery_location: showDeathFields ? basicForm.cemetery_location || null : null,
      grave_number: showDeathFields ? basicForm.grave_number || null : null,
      photoUri: targetPhoto,
      photoBase64: targetBase64,
    });
    if (res.ok) {
      setPhotoUri(null);
      setPhotoBase64(null);
      CustomAlert.alert('نجاح', res.message);
    } else {
      CustomAlert.alert('خطأ', res.message);
    }
  };

  const handleAddContact = async () => {
    if (!contactForm.value.trim()) {
      CustomAlert.alert('تنبيه', 'يرجى إدخال قيمة حساب التواصل.');
      return;
    }
    const res = await addContact({
      type: contactForm.type,
      value: contactForm.value.trim(),
      label: contactForm.label.trim() || null,
    });
    if (res.ok) {
      setContactForm({ type: contactForm.type, value: '', label: '' });
      const extra = contactForm.type === 'whatsapp' ? '\n\nيمكنك الآن تسجيل الدخول عبر OTP بهذا الرقم.' : '';
      CustomAlert.alert('نجاح', res.message + extra);
    } else {
      CustomAlert.alert('خطأ', res.message);
    }
  };

  const handleAddChild = async () => {
    if (!childForm.first_name.trim()) {
      CustomAlert.alert('تنبيه', 'يرجى إدخال اسم الابن/الابنة.');
      return;
    }
    if (!childForm.parentChoiceId || childForm.parentChoiceId <= 0) {
      if (person?.gender === 'female') {
        const hasHusbands = Boolean(data?.husband_candidates && data.husband_candidates.length > 0);
        if (!hasHusbands) {
          CustomAlert.alert('تنبيه', 'يجب توثيق عقد الزواج أولاً من تبويب "سجلات الزواج" لتتمكني من إضافة الأبناء ونسبهم لوالدهم.');
        } else {
          CustomAlert.alert('تنبيه', 'يرجى اختيار الأب من قائمة الأزواج المسجلين.');
        }
      } else {
        CustomAlert.alert('تنبيه', 'يرجى اختيار الأم من قائمة الزوجات المسجلات.');
      }
      return;
    }
    const payload =
      person?.gender === 'male'
        ? { ...childForm, first_name: childForm.first_name.trim(), mother_id: childForm.parentChoiceId }
        : { ...childForm, first_name: childForm.first_name.trim(), parent_id: childForm.parentChoiceId };
    const res = await addChild(payload);
    if (res.ok) {
      setChildForm({ first_name: '', gender: 'male', birth_date: '', parentChoiceId: childForm.parentChoiceId });
      CustomAlert.alert('نجاح', res.message);
    } else {
      CustomAlert.alert('خطأ', res.message);
    }
  };

  const handleAddMarriage = async () => {
    const isWoman = person?.gender === 'female';
    if (marriageForm.spouseType === 'existing') {
      if (!marriageForm.spouseId) {
        CustomAlert.alert('تنبيه', isWoman ? 'يرجى البحث واختيار الزوج من القائمة.' : 'يرجى البحث واختيار الزوجة من القائمة.');
        return;
      }
      const payload: StoreMarriageInput = {
        spouse_type: 'existing',
        married_at: marriageForm.married_at || null,
        ...(isWoman
          ? { husband_id: marriageForm.spouseId }
          : { wife_id: marriageForm.spouseId }),
      };
      const res = await addMarriage(payload);
      if (res.ok) {
        setMarriageForm((f) => ({ ...f, married_at: '', searchQuery: '', spouseId: 0 }));
        CustomAlert.alert('نجاح', res.message);
      } else {
        CustomAlert.alert('خطأ', res.message);
      }
    } else if (marriageForm.spouseType === 'outside_family') {
      if (!marriageForm.firstName.trim()) {
        CustomAlert.alert('تنبيه', isWoman ? 'يرجى إدخال اسم الزوج الأول.' : 'يرجى إدخال اسم الزوجة الأول.');
        return;
      }
      const payload: StoreMarriageInput = {
        spouse_type: 'outside_family',
        first_name: marriageForm.firstName.trim(),
        last_name: marriageForm.lastName.trim() || null,
        birth_date: marriageForm.birthDate || null,
        photoUri: marriageForm.photoUri || null,
        married_at: marriageForm.married_at || null,
      };
      const res = await addMarriage(payload);
      if (res.ok) {
        setMarriageForm((f) => ({
          ...f,
          firstName: '',
          lastName: '',
          birthDate: '',
          photoUri: null,
          married_at: '',
        }));
        CustomAlert.alert('نجاح', res.message);
      } else {
        CustomAlert.alert('خطأ', res.message);
      }
    } else if (marriageForm.spouseType === 'inside_family') {
      if (!marriageForm.firstName.trim()) {
        CustomAlert.alert('تنبيه', isWoman ? 'يرجى إدخال اسم الزوج الأول.' : 'يرجى إدخال اسم الزوجة الأول.');
        return;
      }
      if (!marriageForm.parentId) {
        CustomAlert.alert('تنبيه', isWoman ? 'يرجى اختيار والد الزوج من رجال العائلة لتوثيق نسبه.' : 'يرجى اختيار والد الزوجة من رجال العائلة لتوثيق نسبها.');
        return;
      }
      const payload: StoreMarriageInput = {
        spouse_type: 'inside_family',
        first_name: marriageForm.firstName.trim(),
        parent_id: marriageForm.parentId,
        birth_date: marriageForm.birthDate || null,
        photoUri: marriageForm.photoUri || null,
        married_at: marriageForm.married_at || null,
      };
      const res = await addMarriage(payload);
      if (res.ok) {
        setMarriageForm((f) => ({
          ...f,
          firstName: '',
          birthDate: '',
          photoUri: null,
          married_at: '',
          parentId: 0,
        }));
        CustomAlert.alert('نجاح', res.message);
      } else {
        CustomAlert.alert('خطأ', res.message);
      }
    }
  };

  const handleAddLocation = async () => {
    if (!locationForm.location_id) {
      CustomAlert.alert('تنبيه', 'يرجى اختيار المكان.');
      return;
    }
    const res = await addLocation({
      location_id: locationForm.location_id,
      label: locationForm.label.trim() || null,
      url: locationForm.url.trim() || null,
    });
    if (res.ok) {
      setLocationForm((f) => ({ ...f, label: '', url: '' }));
      CustomAlert.alert('نجاح', res.message);
    } else {
      CustomAlert.alert('خطأ', res.message);
    }
  };

  const handleSavePrivacy = async () => {
    const contact_visibilities = Object.entries(contactVisibilities).map(([id, visibility]) => ({
      id: Number(id),
      visibility,
    }));

    const res = await updatePrivacy({
      ...privacyForm,
      contact_visibilities,
    });

    if (res.ok) {
      setShowPrivacyModal(false);
      CustomAlert.alert('نجاح', res.message);
    } else {
      CustomAlert.alert('خطأ', res.message);
    }
  };

  const cardStyle = [
    styles.card,
    {
      backgroundColor: isDark ? colors.card : '#FFFFFF',
      borderColor: isDark ? '#334155' : colors.border,
    },
  ];

  if (!hasIdentity && status !== 'loading') {
    return <AuthGate lock={lock} />;
  }

  return (
    <ThemedView style={styles.container}>
      <View style={{ paddingTop: Platform.OS === 'ios' ? 40 : 10, paddingHorizontal: 16 }}>
        <HeaderBar title="حسابي الشخصي" showBack onBackPress={() => router.back()} />
      </View>

      {status === 'loading' ? (
        <View style={styles.centered}>
          <ActivityIndicator size="large" color={colors.primary} />
          <Text style={[styles.loadingText, { color: colors.icon }]}>جاري تحميل ملفك الشخصي...</Text>
        </View>
      ) : status === 'forbidden' || status === 'error' ? (
        <ProfileStatusGate status={status} errorMessage={errorMessage} lock={lock} reload={reload} />
      ) : data && person ? (
        <ScrollView
          showsVerticalScrollIndicator={false}
          contentContainerStyle={styles.scrollContent}
          keyboardShouldPersistTaps="handled">

          {/* Executive Hero Banner */}
          <ProfileHero
            data={data}
            person={person}
            saving={saving}
            photoUri={photoUri}
            photoBase64={photoBase64}
            authBadge={authBadge}
            setActiveTab={setActiveTab}
            pickPhoto={pickPhoto}
          />

          {/* Parents Lock Card (Read Only Lineage) */}
          <ParentsCard data={data} cardStyle={cardStyle} />

          {/* Dedicated High-Visibility Privacy & Security Banner — مخفي مؤقتًا */}
          {false && (
            <PrivacyBanner setShowPrivacyModal={setShowPrivacyModal} />
          )}

          {/* Modern Segmented Tab Pills Bar */}
          <ProfileSectionTabs
            active={activeTab}
            onChange={setActiveTab}
            counts={{
              contacts: data.contacts.length,
              children: data.children.length,
              marriages: data.marriages.length,
              locations: data.locations.length,
            }}
          />

          {/* TAB 1: BASIC INFORMATION */}
          {activeTab === 'basic' && (
            <BasicTab
              person={person}
              basicForm={basicForm}
              setBasicForm={setBasicForm}
              showDeathFields={showDeathFields}
              setShowDeathFields={setShowDeathFields}
              pickPhoto={pickPhoto}
              handleSaveBasic={handleSaveBasic}
              saving={saving}
              photoUri={photoUri}
              cardStyle={cardStyle}
            />
          )}

          {/* TAB 2: CONTACT ACCOUNTS */}
          {activeTab === 'contacts' && (
            <ContactsTab
              data={data}
              contactForm={contactForm}
              setContactForm={setContactForm}
              removeContact={removeContact}
              handleAddContact={handleAddContact}
              saving={saving}
              cardStyle={cardStyle}
            />
          )}

          {/* TAB 3: CHILDREN */}
          {activeTab === 'children' && (
            <ChildrenTab
              data={data}
              person={person}
              childForm={childForm}
              setChildForm={setChildForm}
              handleAddChild={handleAddChild}
              saving={saving}
              cardStyle={cardStyle}
            />
          )}

          {/* TAB 4: MARRIAGES */}
          {activeTab === 'marriages' && (
            <MarriagesTab
              data={data}
              marriageForm={marriageForm}
              setMarriageForm={setMarriageForm}
              marriageEdits={marriageEdits}
              setMarriageEdits={setMarriageEdits}
              patchMarriage={patchMarriage}
              handleAddMarriage={handleAddMarriage}
              saving={saving}
              cardStyle={cardStyle}
            />
          )}

          {/* TAB 5: LOCATIONS */}
          {activeTab === 'locations' && (
            <LocationsTab
              data={data}
              locationForm={locationForm}
              setLocationForm={setLocationForm}
              handleAddLocation={handleAddLocation}
              removeLocation={removeLocation}
              saving={saving}
              cardStyle={cardStyle}
            />
          )}



        </ScrollView>
      ) : null}

      <ProfilePrivacyModal
        visible={showPrivacyModal}
        onClose={() => setShowPrivacyModal(false)}
        privacyForm={privacyForm}
        setPrivacyForm={setPrivacyForm}
        contactVisibilities={contactVisibilities}
        setContactVisibilities={setContactVisibilities}
        contacts={data?.contacts ?? []}
        onSave={handleSavePrivacy}
        saving={saving}
      />
    </ThemedView>
  );
}

