# API documentation

## Authentication

**[POST]** /auth-token

Body
```json
{
    "username": "user",
    "password": "secret"
}
```
Response
```json
{
    "acknowledged": true,
    "authToken": "CjAGEIupZtlkbzB3XF9t1wKoNCHztTsmb1XOM2fvKWLL7Jt8ZaU3ke7mWuBWwWp1mps=",
    "success": true
}
```

## Upload file

**[POST]** /api/file
Headers
```
X-Auth-Token=CjAGEIupZtlkbzB3XF9t1wKoNCHztTsmb1XOM2fvKWLL7Jt8ZaU3ke7mWuBWwWp1mps=
```
Body (form-data)
```
upload=uploaded file
filename=example.pdf
type=application/pdf
```
Response
```json
{
    "uploaded": 1,
    "fileName": "davmat_137769.pdf",
    "hash": "233d23ef102ec6a6549f7c0c2ee60e2d8f54e297",
    "url": "/data/file/view/233d23ef102ec6a6549f7c0c2ee60e2d8f54e297?name=davmat_137769.pdf&type=application/pdf"
}
```