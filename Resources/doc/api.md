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


## Hello world example
Hello world:

```
curl --header "Accept: application/json" --header "X-Auth-Token: y03x33WesxeubKkKqoiGChQL44KyoUESml/kES+q+QS79P798OPHyTxugl8+e+IUcq8=" https://admin.test/api/test
```

## Get user information

Get user information (username, display name, roles and email):

```
curl --header "Accept: application/json" --header "X-Auth-Token: y03x33WesxeubKkKqoiGChQL44KyoUESml/kES+q+QS79P798OPHyTxugl8+e+IUcq8=" https://admin.test/api/user-profile
```

Get all user profiles (only for roles )
```
curl --header "Accept: application/json" --header "X-Auth-Token: y03x33WesxeubKkKqoiGChQL44KyoUESml/kES+q+QS79P798OPHyTxugl8+e+IUcq8=" https://admin.test/api/user-profiles
```
