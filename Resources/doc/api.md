# API documentation

## Authentication

**[POST]** /auth-token
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