# AI Assistant - Arsitektur & Cara Kerja

## 🏗️ Arsitektur Overview

AI Assistant **TIDAK membuat API endpoint**. Ini adalah **Filament Page** yang bekerja sebagai **Livewire Component** dan mengambil data **langsung dari database** menggunakan **Eloquent queries**.

```
┌─────────────────────────────────────────────────────────────┐
│                    USER (Browser)                            │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       │ HTTP Request (Livewire)
                       ▼
┌─────────────────────────────────────────────────────────────┐
│         Filament Page (AiAssistant.php)                      │
│         - Livewire Component                                 │
│         - UI Chat Interface                                   │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       │ Method Call
                       ▼
┌─────────────────────────────────────────────────────────────┐
│         AiAnalyticsService                                   │
│         - Mengumpulkan data dari database                    │
│         - Membangun context JSON                             │
│         - Memanggil AI Client                                │
└──────────────────────┬──────────────────────────────────────┘
                       │
        ┌──────────────┴──────────────┐
        │                               │
        ▼                               ▼
┌───────────────┐              ┌──────────────────┐
│   Database    │              │  GeminiAiClient  │
│  (MySQL)      │              │  (HTTP Request)   │
│               │              │                   │
│ - orders      │              │  POST to Gemini   │
│ - order_items │              │  API              │
│ - products    │              │                   │
│ - cogs_history│              │  Get AI Response │
│ - stock_levels│              │                   │
└───────────────┘              └──────────────────┘
```

## 📊 Flow Detail

### 1. **User Input** (Browser → Filament Page)

```
User mengetik pertanyaan di chat interface
    ↓
Livewire form submit (wire:submit.prevent="sendQuestion")
    ↓
Method sendQuestion() dipanggil di AiAssistant.php
```

**File**: `app/Filament/Owner/Pages/AiAssistant.php`

```php
public function sendQuestion(): void
{
    // 1. Validasi input
    $this->validate([
        'question' => ['required', 'string', 'max:2000'],
    ]);

    // 2. Ambil user & resolve date range
    $user = auth()->user();
    [$from, $to] = $this->resolveDateRange();

    // 3. Panggil service
    $service = app(AiAnalyticsService::class);
    $answer = $service->analyze(
        $this->question,
        $this->storeId,
        $from,
        $to,
        $user,
    );

    // 4. Tambahkan response ke messages array
    $this->messages[] = [
        'role' => 'assistant',
        'content' => $answer,
        'created_at' => now()->toDateTimeString(),
    ];
}
```

### 2. **Data Collection** (Service → Database)

**File**: `app/Services/Ai/AiAnalyticsService.php`

Service mengambil data **langsung dari database** menggunakan **Eloquent queries**:

```php
public function analyze(string $question, ?string $storeId, ?Carbon $from, ?Carbon $to, ?User $user = null): string
{
    // 1. Ambil tenant_id dari user
    $tenantId = $user->currentTenant()?->id;

    // 2. Validasi store (jika dipilih)
    if ($storeId) {
        $store = Store::where('id', $storeId)
            ->where('tenant_id', $tenantId)
            ->first();
    }

    // 3. Build context dari database
    $context = $this->buildContext($tenantId, $storeId, $storeName, $from, $to);
    
    // 4. Build prompt
    $prompt = $this->buildPrompt($context, $question);
    
    // 5. Panggil AI client
    return $this->client->ask($prompt);
}
```

#### Data yang Diambil dari Database:

**a. Sales Summary** (`getSalesSummary`)
```php
Order::query()
    ->where('tenant_id', $tenantId)
    ->where('status', 'completed')
    ->whereBetween('completed_at', [$from, $to])
    ->sum('subtotal');  // Total penjualan
```

**b. Sales by Day** (`getSalesByDay`)
```php
Order::query()
    ->select(
        DB::raw("DATE(COALESCE(completed_at, created_at)) as date"),
        DB::raw("SUM(subtotal) as total_sales"),
        DB::raw("COUNT(*) as order_count")
    )
    ->groupBy(DB::raw("DATE(COALESCE(completed_at, created_at))"))
    ->get();
```

**c. Top Products** (`getTopProducts`)
```php
OrderItem::query()
    ->whereIn('order_id', $orderIds)
    ->join('products', 'order_items.product_id', '=', 'products.id')
    ->select(
        'products.name',
        DB::raw("SUM(order_items.quantity) as qty_sold"),
        DB::raw("SUM(order_items.total_price) as revenue")
    )
    ->groupBy('products.name')
    ->orderByDesc('revenue')
    ->limit(10)
    ->get();
```

**d. COGS Summary** (`getCogsSummary`)
```php
CogsHistory::query()
    ->where('tenant_id', $tenantId)
    ->whereNotNull('order_id')
    ->whereBetween('created_at', [$from, $to])
    ->sum('total_cogs');
```

**e. Low Stock Items** (`getLowStockItems`)
```php
StockLevel::query()
    ->join('inventory_items', 'stock_levels.inventory_item_id', '=', 'inventory_items.id')
    ->join('stores', 'stock_levels.store_id', '=', 'stores.id')
    ->where('stock_levels.tenant_id', $tenantId)
    ->whereRaw('stock_levels.current_stock <= stock_levels.min_stock_level')
    ->limit(10)
    ->get();
```

### 3. **Context Building**

Data dari database dikumpulkan menjadi **context JSON**:

```php
$context = [
    'meta' => [
        'tenant_id' => $tenantId,
        'store_id' => $storeId,
        'store_name' => $storeName,
        'date_range' => [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ],
    ],
    'sales_summary' => [...],
    'sales_by_day' => [...],
    'top_products' => [...],
    'cogs_summary' => [...],
    'low_stock_items' => [...],
];
```

### 4. **Prompt Building**

Context JSON + pertanyaan user dijadikan **prompt** untuk AI:

```php
$prompt = <<<PROMPT
Kamu adalah asisten data analyst untuk sebuah bisnis F&B.

KONTEKS DATA:
- Periode: HARI INI (2025-11-23)
- Scope: untuk semua toko

Berikut data dalam JSON:
{$contextJson}

Pertanyaan user:
"{$question}"
PROMPT;
```

### 5. **AI API Call** (GeminiAiClient → Gemini API)

**File**: `app/Services/Ai/Clients/GeminiAiClient.php`

```php
public function ask(string $prompt): string
{
    // HTTP Request ke Gemini API
    $response = Http::timeout(30)
        ->withHeaders([
            'Content-Type' => 'application/json',
            'X-goog-api-key' => $this->apiKey,
        ])
        ->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent', [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ]
        ]);

    // Extract response text
    return $response->json()['candidates'][0]['content']['parts'][0]['text'];
}
```

### 6. **Response Display** (Livewire → Browser)

Response dari AI dikembalikan ke Livewire component:

```php
// Di AiAssistant.php
$this->messages[] = [
    'role' => 'assistant',
    'content' => $answer,  // Response dari Gemini
    'created_at' => now()->toDateTimeString(),
];
```

Livewire otomatis **re-render** view dan menampilkan response di chat interface.

## 🔄 Complete Flow Diagram

```
┌─────────────┐
│   Browser   │
│  (User UI)  │
└──────┬──────┘
       │
       │ 1. User ketik pertanyaan
       │    & klik "Kirim"
       ▼
┌─────────────────────────────────────┐
│  Filament Page (Livewire)            │
│  AiAssistant.php                     │
│  ┌───────────────────────────────┐   │
│  │ sendQuestion()                │   │
│  │ - Validate input               │   │
│  │ - Resolve date range           │   │
│  │ - Call AiAnalyticsService      │   │
│  └───────────┬───────────────────┘   │
└──────────────┼───────────────────────┘
               │
               │ 2. Method call
               ▼
┌─────────────────────────────────────┐
│  AiAnalyticsService                │
│  ┌───────────────────────────────┐   │
│  │ analyze()                      │   │
│  │                                │   │
│  │ 3. Build Context:             │   │
│  │    - getSalesSummary()         │   │
│  │    - getSalesByDay()           │   │
│  │    - getTopProducts()          │   │
│  │    - getCogsSummary()          │   │
│  │    - getLowStockItems()        │   │
│  │                                │   │
│  │ 4. Build Prompt                │   │
│  │    (Context JSON + Question)   │   │
│  │                                │   │
│  │ 5. Call GeminiAiClient        │   │
│  └───────────┬───────────────────┘   │
└──────────────┼───────────────────────┘
               │
       ┌───────┴────────┐
       │                │
       ▼                ▼
┌─────────────┐  ┌──────────────┐
│  Database   │  │ Gemini API   │
│  (MySQL)     │  │              │
│             │  │ 6. HTTP POST  │
│  Eloquent   │  │    with       │
│  Queries    │  │    prompt     │
│             │  │              │
│  - orders   │  │ 7. Get AI    │
│  - products │  │    response  │
│  - cogs_... │  │              │
└─────────────┘  └──────┬───────┘
                        │
                        │ 8. Return response
                        ▼
┌─────────────────────────────────────┐
│  AiAnalyticsService                 │
│  ┌───────────────────────────────┐   │
│  │ return $answer                 │   │
│  └───────────┬───────────────────┘   │
└──────────────┼───────────────────────┘
               │
               │ 9. Add to messages
               ▼
┌─────────────────────────────────────┐
│  AiAssistant.php                     │
│  ┌───────────────────────────────┐     │
│  │ $this->messages[] = [...]    │     │
│  └───────────┬───────────────────┘     │
└──────────────┼───────────────────────┘
               │
               │ 10. Livewire re-render
               ▼
┌─────────────┐
│   Browser   │
│  (Display   │
│   Response) │
└─────────────┘
```

## 🔑 Key Points

### ✅ **TIDAK Ada API Endpoint**
- Ini adalah **Filament Page** (Livewire Component)
- Tidak ada route `/api/ai-assistant` atau sejenisnya
- Semua komunikasi via **Livewire** (WebSocket/HTTP polling)

### ✅ **Data Langsung dari Database**
- Menggunakan **Eloquent ORM** untuk query database
- Tidak ada caching (data real-time)
- Semua query **tenant-filtered** untuk security

### ✅ **Single Request Flow**
- User submit → Service collect data → Call Gemini API → Return response
- Semua dalam **satu request cycle**
- Tidak ada background job atau queue

### ✅ **Real-time Data**
- Data diambil **setiap kali** user bertanya
- Tidak ada cache, selalu fresh data
- Filter (store, date range) diterapkan langsung di query

## 📁 File Structure

```
app/
├── Filament/Owner/Pages/
│   └── AiAssistant.php          # Livewire component (UI)
│
├── Services/Ai/
│   ├── AiAnalyticsService.php   # Main service (data collection)
│   └── Clients/
│       ├── AiClientInterface.php # Interface
│       ├── DummyAiClient.php    # Dummy implementation
│       └── GeminiAiClient.php    # Gemini API client
│
resources/views/filament/owner/pages/
└── ai-assistant.blade.php       # Blade template (UI)

config/
└── ai.php                        # AI configuration
```

## 🔒 Security & Multi-tenancy

1. **Tenant Filtering**
   ```php
   ->where('tenant_id', $tenantId)  // Semua query
   ```

2. **Store Validation**
   ```php
   Store::where('id', $storeId)
       ->where('tenant_id', $tenantId)  // Pastikan store milik tenant
       ->first();
   ```

3. **User Authentication**
   ```php
   $user = auth()->user();  // Hanya user yang login
   ```

4. **No Direct Database Access**
   - Semua via Eloquent (ORM protection)
   - Tidak ada raw SQL queries

## ⚡ Performance Considerations

1. **Query Optimization**
   - Menggunakan `select()` untuk limit columns
   - Menggunakan `limit()` untuk top products/low stock
   - Menggunakan `groupBy()` untuk aggregations

2. **No Caching**
   - Data selalu fresh
   - Trade-off: lebih lambat tapi lebih akurat

3. **API Timeout**
   - Gemini API timeout: 30 detik
   - Jika timeout, return error message

## 🚀 Future Improvements

1. **Caching**
   - Cache context data untuk periode yang sama
   - Reduce database queries

2. **Background Jobs**
   - Move AI API call ke queue
   - Show loading indicator

3. **API Endpoint** (Optional)
   - Jika perlu diakses dari mobile app
   - Create REST API endpoint

---

**Last Updated**: 2025-01-27  
**Architecture**: Filament Page (Livewire) → Service → Database + Gemini API

