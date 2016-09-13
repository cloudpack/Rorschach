# Rorschach


## Rorschachとは

* Yamlファイルを基準にWebAPIのテストを行うツール
* 以下の意味合いを込めてある
    + [アメコミの登場人物](https://ja.wikipedia.org/wiki/%E3%82%A6%E3%82%A9%E3%83%83%E3%83%81%E3%83%A1%E3%83%B3#.E3.82.AF.E3.83.A9.E3.82.A4.E3.83.A0.E3.83.90.E3.82.B9.E3.82.BF.E3.83.BC.E3.82.BA)の絶対に妥協しない性格
    + [性格診断](https://ja.wikipedia.org/wiki/%E3%83%AD%E3%83%BC%E3%83%AB%E3%82%B7%E3%83%A3%E3%83%83%E3%83%8F%E3%83%BB%E3%83%86%E3%82%B9%E3%83%88)

## Install

```
composer require --dev cloudpack/Rorschach
```

## Usage

### Basic
```
./vendor/cloudpack/rorschach/console inspect
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
デフォルトでは、プロジェクトディレクトリの `test*.yml` すべてを対象とする。
特定ファイル指定をしたい場合はコマンドライン引数で指定することが可能。

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
