# Rorschach


## Rorschachとは

* Yamlファイルを基準にWebAPIのテストを行うツール
* 以下の意味合いを込めてある
    + [アメコミの登場人物](https://ja.wikipedia.org/wiki/%E3%82%A6%E3%82%A9%E3%83%83%E3%83%81%E3%83%A1%E3%83%B3#.E3.82.AF.E3.83.A9.E3.82.A4.E3.83.A0.E3.83.90.E3.82.B9.E3.82.BF.E3.83.BC.E3.82.BA)の絶対に妥協しない性格
    + [性格診断](https://ja.wikipedia.org/wiki/%E3%83%AD%E3%83%BC%E3%83%AB%E3%82%B7%E3%83%A3%E3%83%83%E3%83%8F%E3%83%BB%E3%83%86%E3%82%B9%E3%83%88)

## Install

```bash
composer require --dev cloudpack/rorschach
```

## Usage

### Basic
```bash
./vendor/bin/rorschach inspect
```

### Options

#### saikou
普通にテストした場合、 `finished.` という味気ないメッセージが流れます。
少しでもあなたとの距離を縮める為に最高のオプションを用意いたしました。
`--saikou` か `-s` をつけて是非お試しください。


#### bind
Yaml内に、 `{{ }}` ブラケットで囲んだ変数を記述することで、外部から値を注入することができる。

注入する方法は２つ

1. `--bind` を使ってJSONにて指定する。(複数指定可能)

```bash
--bind='{"env": "prod"}'
```

```bash
--bind='{"env": "prod"}' --bind='{"api-token": "YOURTOKEN"}'
```

2. pre-requestsのbinding構文を使う
```yaml
bind:
  api-token: response.data.param
```

上記のようにすることで、以下のレスポンスの `SOME PARAMETER` が `api-token` としてbindされる

```json
{
  "response": {
    "data": {
      "param": "SOME PARAMETER"
    }
  }
}
```


#### file
デフォルトでは、プロジェクトディレクトリの `test*.yml` すべてを対象とする。
特定ファイル指定をしたい場合はコマンドライン引数で指定することが可能。

```bash
--file='test/test-api.yml'
```


### plugin機能ついて
- pre-requestで実行したAPIのレスポンスをフックして任意のコードを実行し、レスポンスを返すようにできる
- venderディレクトリやtestsディレクトリと同階層に `plugins` という名前でディレクトリを作成し、その中にphpファイルを設置する
    - 実行時にpluginsディレクトリ内のphpファイルを読み込む為、関数を定義しyamlでは `after-function` というキーを設定することでフックすることができる
    - e.g.
```
$ tree .
.
├── README.md
├── composer.json
├── composer.lock
├── plugins
│   └── test_function.php
├── tests
│   ├── test-beta.yml
....

$ cat plugins/test_function.php
<?php
function toTest($body) {
    return json_decode($body, true);
}

$ cat ./tests/test-beta.yml
....
pre-request:
  -
    url: /login
    method: POST
    option:
      headers:
        ...
      json:
        ...
    bind:
      api-token: test
    after-function: toTest
...
```
- この機能を利用することにより、次のようなことが可能になる
    - 暗号化されているため復号化したり...
    - 別の固定値に書き換えたり...
    - etc ...

### Yaml Sample
```yaml
base: https://{{ env }}.example.com
option:
  headers:
    x-api-key: YOUR-SECRET-KEY
    ContentType: application/json
  allow_redirects: false
pre-request:
  -
    url: /auth
    method: GET
    option:
      headers:
        x-header: HEADER
      body:
        name: shinichi
        password: p@ssw0rd
    bind:
      api-token: response.data.param
request:
  -
    url: /users/1
    method: GET
    option:
      headers:
        api-token: {{ api-token }}
      body:
        exclude: false
    expect:
      code: 200
      has:
        - id
        - user.name
        - user.address..tel01
      type:
        id: integer|nullable
        name: string
      value:
        id: 123
        name: shinichi
  -
    url: /items
    method: GET
    expect:
      code: 302
      redirect: https://prod.example.com
```
