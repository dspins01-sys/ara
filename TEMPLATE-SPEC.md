# Ara CMS Lightweight Template Spec v3

Template ZIP tetap framework-free. Template adalah **presentation layer**, bukan database content.

## Struktur minimum

```text
my-template.zip
├── manifest.json
├── style.css
├── preview.png              # opsional
└── assets/                  # opsional, gambar block
```

## Prinsip arsitektur

- **Content adalah milik website** dan tersimpan di database.
- **Template hanya mengatur desain, layout, dan starter structure.**
- Ganti template **tidak menghapus atau mengganti content yang sudah ada**.
- Restore Template mengembalikan **layout/presentation**, bukan content.
- Demo/starter content hanya diimport melalui aksi terpisah dan selalu ditambahkan, tidak menggantikan content lama.

## manifest.json

```json
{
  "name": "Starter Corporate",
  "description": "Template corporate ringan.",
  "version": "3.0",
  "settings": {
    "hero_layout": "split",
    "accent_color": "#2563eb",
    "site_theme": "default"
  },
  "blocks": [
    {
      "block_type": "hero",
      "layout": "center",
      "title": "Solusi Digital",
      "subtitle": "SOLUSI",
      "body": "<p>Isi hero...</p>",
      "image": "assets/hero.svg",
      "button_text": "Pelajari",
      "button_url": "#contact"
    }
  ]
}
```

## Block yang didukung

- `hero`
- `feature`
- `text`
- `image-text`
- `gallery`
- `quote`
- `cta`
- `spacer`
- `about`
- `contact`

Layout block:

- `image-right`
- `image-left`
- `center`
- `full`

## Operasi Template di V18

### Gunakan Template
- Mengaktifkan `style.css` dan design settings.
- Content yang sudah ada tetap.
- Block yang cocok berdasarkan `block_type` dapat menerima layout/presentation dari template.
- Block yang tidak cocok tetap dipertahankan dan ditempatkan setelah block yang dipetakan.

### Import Demo
- Menambahkan starter blocks template ke bagian bawah halaman.
- Tidak menghapus atau mengganti block/content yang sudah ada.
- Membuat revision sebelum import.

### Pulihkan Layout Sebelumnya
- Mengembalikan template/skin, layout, typography, dan presentation settings dari revision terakhir.
- **Tidak menghapus atau mengganti content.**

### Reset ke Default
- Menghapus skin custom dan mengembalikan presentation ke Default.
- Content tetap aman.

## Hero

Hero adalah **block biasa** di `sections`:

- bisa edit text
- bisa edit rich text
- bisa ganti gambar
- bisa edit tombol/link
- bisa duplicate
- bisa hide/show
- bisa drag/reorder
- bisa delete
- bisa ditambahkan lagi dari Block Library

## Asset gambar

File gambar di `assets/` disalin ke:

```text
public/assets/images/templates/<slug>/
```

## Batas ringan

- ZIP maksimal 5 MB.
- CSS maksimal 350 KB.
- Maksimal 30 block.
- Maksimal 20 asset gambar.
- Asset gambar maksimal 2 MB/file.
- Tidak menggunakan framework atau build tool.
