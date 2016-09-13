# Rorschach
*RorschachはPHP製のAPIテストツールであーる*


## Usage

### Basic
```
./vendor/Rorschach/console inspect
```

### Options


#### bind
Yaml内に、 `(( ))` ブラケットで囲んだ変数を記述することで、外部から値を注入することができる。

注入する方法は２つ

1. `--bind` を使ってJSONにて指定する。(複数指定可能)

```
--bind='{"env": "prod"}'
```

```
--bind='{"env": "prod"}' --bind='{"api-token": "YOURTOKEN"}'
```

2. pre-requestsのbinding構文を使う
```
bind:
  api-token: response.data.param
```

上記のようにすることで、以下のレスポンスの `SOME PARAMETER` が `api-token` としてbindされる

```
{
  response: {
    data: {
      param: "SOME PARAMETER"
    }
  }
}
```


#### file
```
--file='test-hoge.yml'
```

### Yaml Sample
```
base: https://(( env )).example.com
headers:
  x-api-key: YOUR-SECRET-KEY
  ContentType: application/json
pre-requests:
  -
    resource: /auth
    method: GET
    headers:
      x-header: HEADER
    body:
      name: shinichi
      password: p@ssw0rd
    bind:
      api-token: response.data.param
resources:
  -
    url: /users
    method: GET
    headers:
      api-token: (( api-token ))
    body:
      exclude: false
    expect:
      code: 200
      has:
        - id
        - user.name
        - user.address..tel01
      assert:
        id: integer|nullable
        name: string
  -
    url: /items
    method: GET
    expect:
      code: 302
      redirect: https://prod.example.com
```
