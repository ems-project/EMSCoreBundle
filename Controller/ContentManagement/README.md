Using the rest API
------------------

Hello world:

```
curl --header "Accept: application/json" --header "X-Auth-Token: y03x33WesxeubKkKqoiGChQL44KyoUESml/kES+q+QS79P798OPHyTxugl8+e+IUcq8=" https://admin.test/api/test
```

Get user information (username, display name, roles and email):

```
curl --header "Accept: application/json" --header "X-Auth-Token: y03x33WesxeubKkKqoiGChQL44KyoUESml/kES+q+QS79P798OPHyTxugl8+e+IUcq8=" https://admin.test/api/user-profile
```