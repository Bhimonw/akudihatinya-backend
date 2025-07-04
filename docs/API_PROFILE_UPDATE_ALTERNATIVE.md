# API Profile Update Alternative Documentation

## Overview
Endpoint alternatif untuk update profile dengan validasi dimensi gambar yang lebih fleksibel (maksimal 800x800 piksel).

## Endpoint

### Update Profile Alternative
**Endpoint:** `PUT /api/users/me/alt` atau `POST /api/users/me/alt`  
**Middleware:** `auth:sanctum`  
**Accessible by:** Admin, Puskesmas

#### Request Headers
```
Content-Type: multipart/form-data
Authorization: Bearer {token}
```

#### Request Body
| Field | Type | Required | Validation | Description |
|-------|------|----------|------------|-------------|
| username | string | No | min:3, max:255, unique, regex:/^[a-zA-Z0-9._-]+$/ | Username unik |
| name | string | No | min:2, max:255, regex:/^[\p{L}\p{N}\s]+$/u | Nama user |
| password | string | No | min:8, confirmed, regex pattern | Password baru |
| password_confirmation | string | No | required_with:password | Konfirmasi password |
| profile_picture | file | No | image, max:2MB, dimensions:100x100-800x800 | Foto profil (max 800x800) |
| _method | string | No | in:PUT,PATCH | Method spoofing |

#### Response Success (200)
```json
{
    "success": true,
    "message": "Profil berhasil diperbarui dengan validasi alternatif (max 800x800)",
    "user": {
        "id": 1,
        "username": "admin",
        "name": "Administrator",
        "role": "admin",
        "profile_picture": "profile_pictures/user_1_1234567890.jpg",
        "created_at": "2024-01-01T00:00:00.000000Z",
        "updated_at": "2024-01-01T12:00:00.000000Z"
    },
    "data": {
        "id": 1,
        "username": "admin",
        "name": "Administrator",
        "role": "admin",
        "profile_picture": "profile_pictures/user_1_1234567890.jpg",
        "created_at": "2024-01-01T00:00:00.000000Z",
        "updated_at": "2024-01-01T12:00:00.000000Z"
    }
}
```

#### Response Error (422)
```json
{
    "success": false,
    "message": "Data tidak valid",
    "errors": {
        "profile_picture": [
            "Dimensi gambar harus minimal 100x100 piksel dan maksimal 800x800 piksel."
        ]
    }
}
```

#### Response Error (500)
```json
{
    "success": false,
    "message": "Gagal memperbarui profil. Silakan coba lagi.",
    "error": "Internal server error"
}
```

## Perbedaan dengan Endpoint Utama

| Aspek | Endpoint Utama (`/api/users/me`) | Endpoint Alternatif (`/api/users/me/alt`) |
|-------|----------------------------------|-------------------------------------------|
| Dimensi Gambar | 100x100 - 2000x2000 piksel | 100x100 - 800x800 piksel |
| Ukuran File | Maksimal 2MB | Maksimal 2MB |
| Format File | jpeg, png, jpg, gif, webp | jpeg, png, jpg, gif, webp |
| Use Case | Gambar berkualitas tinggi | Gambar dengan ukuran lebih kecil |

## Kapan Menggunakan Endpoint Alternatif

1. **Ketika gambar terlalu besar** - Jika gambar Anda memiliki dimensi lebih dari 800x800 piksel tetapi kurang dari 2000x2000 piksel
2. **Untuk optimasi performa** - Gambar dengan dimensi lebih kecil akan memuat lebih cepat
3. **Ketika mendapat error 800x800** - Gunakan endpoint ini sebagai solusi alternatif

## Contoh Penggunaan

### cURL
```bash
curl -X PUT \
  http://localhost:8000/api/users/me/alt \
  -H 'Authorization: Bearer your-token-here' \
  -H 'Content-Type: multipart/form-data' \
  -F 'name=John Doe' \
  -F 'profile_picture=@/path/to/image.jpg'
```

### JavaScript (FormData)
```javascript
const formData = new FormData();
formData.append('name', 'John Doe');
formData.append('profile_picture', fileInput.files[0]);

fetch('/api/users/me/alt', {
    method: 'PUT',
    headers: {
        'Authorization': 'Bearer ' + token
    },
    body: formData
})
.then(response => response.json())
.then(data => console.log(data));
```

## Validasi Profile Picture

### Aturan Validasi
- **Type**: Harus berupa file gambar
- **Format**: jpeg, png, jpg, gif, webp
- **Ukuran**: Maksimal 2MB
- **Dimensi**: Minimal 100x100 piksel, maksimal 800x800 piksel

### Error Messages
- `"The profile picture must be an image."` - File bukan gambar
- `"The profile picture may not be greater than 2048 kilobytes."` - File terlalu besar
- `"Dimensi gambar harus minimal 100x100 piksel dan maksimal 800x800 piksel."` - Dimensi tidak sesuai
- `"The profile picture must be a file of type: jpeg, png, jpg, gif, webp."` - Format tidak didukung

## Tips

1. **Resize gambar** sebelum upload jika dimensi lebih dari 800x800 piksel
2. **Kompres gambar** untuk mengurangi ukuran file jika mendekati 2MB
3. **Gunakan format JPEG** untuk foto dengan banyak warna
4. **Gunakan format PNG** untuk gambar dengan transparansi
5. **Test dengan gambar kecil** terlebih dahulu untuk memastikan endpoint berfungsi