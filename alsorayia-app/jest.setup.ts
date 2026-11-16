const mockFsFiles = new Map<string, string>();
const mockFsDirs = new Set<string>(['file:///mock-documents/']);

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

jest.mock('expo-file-system/legacy', () => ({
  documentDirectory: 'file:///mock-documents/',
  cacheDirectory: 'file:///mock-cache/',
  getInfoAsync: jest.fn(async (uri: string) => ({
    exists: mockFsFiles.has(uri) || mockFsDirs.has(uri),
    isDirectory: mockFsDirs.has(uri),
    uri,
  })),
  makeDirectoryAsync: jest.fn(async (uri: string) => {
    const normalized = uri.endsWith('/') ? uri : `${uri}/`;
    mockFsDirs.add(uri);
    mockFsDirs.add(normalized);
  }),
  readAsStringAsync: jest.fn(async (uri: string) => {
    if (!mockFsFiles.has(uri)) {
      throw new Error(`Mock file not found: ${uri}`);
    }
    return mockFsFiles.get(uri)!;
  }),
  writeAsStringAsync: jest.fn(async (uri: string, content: string) => {
    mockFsFiles.set(uri, content);
  }),
  deleteAsync: jest.fn(async (uri: string) => {
    mockFsFiles.delete(uri);
    mockFsDirs.delete(uri);
  }),
  __resetMockFs: () => {
    mockFsFiles.clear();
    mockFsDirs.clear();
    mockFsDirs.add('file:///mock-documents/');
  },
}));

jest.mock('react-native-nitro-modules', () => ({
  NitroModules: {},
}));

jest.mock('@kingstinct/react-native-healthkit', () => ({
  isHealthDataAvailable: jest.fn(() => true),
  isHealthDataAvailableAsync: jest.fn(async () => true),
  requestAuthorization: jest.fn(async () => true),
  queryStatisticsCollectionForQuantity: jest.fn(async () => []),
  queryStatisticsForQuantity: jest.fn(async () => ({})),
  default: {
    isHealthDataAvailable: jest.fn(() => true),
    isHealthDataAvailableAsync: jest.fn(async () => true),
    requestAuthorization: jest.fn(async () => true),
    queryStatisticsCollectionForQuantity: jest.fn(async () => []),
  },
}));
