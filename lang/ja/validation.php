<?php

return [
    'required' => ':attributeを入力してください',

    'email' => ':attributeはメール形式で入力してください',

    'min' => [
        'string' => ':attributeは:min文字以上で入力してください',
    ],

    'max' => [

        'string' => ':attributeは:max文字以内で入力してください',

    ],

    'unique' => ':attributeは既に登録されています',

    'confirmed' => 'パスワードが一致しません',

    'attributes' => [
        'name' => 'お名前',
        'email' => 'メールアドレス',
        'password' => 'パスワード',
        'password_confirmation' => '確認用パスワード',
    ],
];
