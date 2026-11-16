import { ThemedText } from '@/components/themed-text';
import { IconSymbol } from '@/components/ui/icon-symbol';
import { silaTheme, type ThemeColors } from '@/constants/theme';
import React, { useEffect, useState } from 'react';
import {
  Dimensions,
  LayoutAnimation,
  Pressable,
  StyleSheet,
  View,
  type StyleProp,
  type TextStyle,
  type ViewStyle,
} from 'react-native';


const { width: SCREEN_WIDTH } = Dimensions.get('window');

/** هامش الجدول 16 + حشو اللوحة 16 من كل جانب */
export const TABLE_HTML_CONTENT_WIDTH = SCREEN_WIDTH - 64;

export type TableColumn<T> = {
  key: string;
  title: string;
  flex?: number;
  width?: number;
  render: (item: T) => React.ReactNode;
};

export type ExpandableDataTableProps<T> = {
  colors: ThemeColors;
  sectionTitle: string;
  columns: TableColumn<T>[];
  data: T[];
  keyExtractor: (item: T) => string | number;
  renderExpanded?: (item: T) => React.ReactNode;
  /** عمود يظهر بجانبه شيفرون التوسيع */
  primaryColumnKey: string;
};

type CellTextProps = {
  color: string;
  children: React.ReactNode;
  numberOfLines?: number;
  style?: StyleProp<TextStyle>;
};

export function TablePrimaryText({ color, children, style }: CellTextProps) {
  return (
    <ThemedText style={[styles.primaryText, { color }, style]}>{children}</ThemedText>
  );
}

export function TableMutedText({ color, children, numberOfLines = 3, style }: CellTextProps) {
  return (
    <ThemedText style={[styles.mutedText, { color }, style]} numberOfLines={numberOfLines}>
      {children}
    </ThemedText>
  );
}

export function TableMapButton({
  colors,
  url,
  onPress,
}: {
  colors: ThemeColors;
  url: string | null | undefined;
  onPress: () => void;
}) {
  if (!url) {
    return <ThemedText style={[styles.mutedText, { color: colors.icon }]}>—</ThemedText>;
  }

  return (
    <Pressable
      style={styles.mapBtn}
      onPress={(e) => {
        e.stopPropagation?.();
        onPress();
      }}
      hitSlop={8}
      accessibilityRole="button"
      accessibilityLabel="فتح الخريطة"
    >
      <View style={[styles.mapBtnInner, { backgroundColor: silaTheme.primary + '22' }]}>
        <IconSymbol name="mappin.circle.fill" size={22} color={silaTheme.primary} />
      </View>
    </Pressable>
  );
}

function columnStyle(col: { flex?: number; width?: number }): ViewStyle {
  if (col.width != null) return { width: col.width };
  return { flex: col.flex ?? 1, minWidth: 0 };
}

export function ExpandableDataTable<T>({
  colors,
  sectionTitle,
  columns,
  data,
  keyExtractor,
  renderExpanded,
  primaryColumnKey,
}: ExpandableDataTableProps<T>) {
  const [expandedId, setExpandedId] = useState<string | number | null>(null);

  useEffect(() => {
    setExpandedId(null);
  }, [data]);

  if (!data.length) return null;

  const toggleRow = (id: string | number) => {
    LayoutAnimation.configureNext(LayoutAnimation.Presets.easeInEaseOut);
    setExpandedId((prev) => (prev === id ? null : id));
  };

  return (
    <View style={styles.section}>
      <View style={styles.sectionHeader}>
        <ThemedText style={[styles.sectionTitle, { color: colors.text }]}>{sectionTitle}</ThemedText>
      </View>

      <View style={[styles.table, { backgroundColor: colors.card, borderColor: colors.border }]}>
        <View style={styles.header}>
          {columns.map((col) => (
            <View
              key={col.key}
              style={[
                styles.headerCell,
                columnStyle(col),
                col.width != null ? styles.fixedCell : null,
              ]}
            >
              <ThemedText
                style={[styles.headerText, col.width != null ? styles.headerTextCenter : null]}
              >
                {col.title}
              </ThemedText>
            </View>
          ))}
        </View>

        {data.map((item, index) => {
          const id = keyExtractor(item);
          const isExpanded = expandedId === id;
          const isLast = index === data.length - 1;
          const expandedContent = isExpanded && renderExpanded ? renderExpanded(item) : null;

          return (
            <View
              key={String(id)}
              style={[
                styles.rowGroup,
                { borderBottomColor: colors.border },
                isLast && !isExpanded && { borderBottomWidth: 0 },
              ]}
            >
              <Pressable
                style={({ pressed }) => [styles.row, pressed ? { opacity: 0.78 } : null]}
                onPress={() => toggleRow(id)}
                android_ripple={{ color: 'rgba(0,0,0,0.06)' }}
                accessibilityRole="button"
                accessibilityState={{ expanded: isExpanded }}
              >
                {columns.map((col) => {
                  const isPrimary = col.key === primaryColumnKey;

                  if (isPrimary) {
                    return (
                      <View key={col.key} style={[styles.primaryCell, columnStyle(col)]}>
                        <View
                          style={[
                            styles.chevronWrap,
                            { transform: [{ rotate: isExpanded ? '-90deg' : '180deg' }] },
                          ]}
                        >
                          <IconSymbol name="chevron.left" size={15} color={colors.icon} />
                        </View>
                        <View style={styles.primaryContent}>{col.render(item)}</View>
                      </View>
                    );
                  }

                  return (
                    <View
                      key={col.key}
                      style={[
                        styles.cell,
                        columnStyle(col),
                        col.width != null ? styles.fixedCell : null,
                      ]}
                    >
                      {col.render(item)}
                    </View>
                  );
                })}
              </Pressable>

              {expandedContent ? (
                <View
                  style={[
                    styles.expandedPanel,
                    {
                      backgroundColor: silaTheme.primary + '0C',
                      borderTopColor: colors.border,
                    },
                  ]}
                >
                  {expandedContent}
                </View>
              ) : null}
            </View>
          );
        })}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  section: {
    marginBottom: 24,
  },
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 16,
    marginBottom: 12,
  },
  sectionTitle: {
    fontSize: 18,
    fontFamily: 'Tajawal-Bold',
  },
  table: {
    marginHorizontal: 16,
    borderRadius: 16,
    borderWidth: 1,
    overflow: 'hidden',
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: silaTheme.primary,
    paddingVertical: 13,
    paddingHorizontal: 12,
  },
  headerCell: {
    paddingHorizontal: 4,
    justifyContent: 'center',
  },
  headerText: {
    fontSize: 12,
    fontFamily: 'Tajawal-Bold',
    color: '#FFFFFF',
  },
  headerTextCenter: {
    textAlign: 'center',
  },
  fixedCell: {
    alignItems: 'center',
  },
  rowGroup: {
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    paddingVertical: 14,
    paddingHorizontal: 12,
  },
  cell: {
    paddingHorizontal: 4,
    justifyContent: 'center',
  },
  primaryCell: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 4,
    paddingHorizontal: 4,
  },
  chevronWrap: {
    width: 16,
    marginTop: 3,
    alignItems: 'center',
    justifyContent: 'center',
  },
  primaryContent: {
    flex: 1,
    minWidth: 0,
  },
  primaryText: {
    fontSize: 13,
    fontFamily: 'Tajawal-Bold',
    lineHeight: 20,
  },
  mutedText: {
    fontSize: 12,
    fontFamily: 'Tajawal-Regular',
    lineHeight: 18,
  },
  mapBtn: {
    width: 44,
    alignItems: 'center',
    justifyContent: 'center',
    paddingTop: 2,
  },
  mapBtnInner: {
    padding: 6,
    borderRadius: 10,
  },
  expandedPanel: {
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderTopWidth: StyleSheet.hairlineWidth,
  },
});
