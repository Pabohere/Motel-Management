# Motel Management

မိုတယ်ဝန်ထမ်းသုံး internal software — Laravel 13 / PHP 8.3+ / Blade / SQLite။ မြန်မာစာ UI၊ MMK ငွေကြေး၊ Asia/Yangon အချိန်။ CSS နှင့် JavaScript ကို local မှ ဖတ်သည်။ Node/npm build မလိုပါ။

## ယခုစက်တွင် စတင်အသုံးပြုရန်

Dependencies, application key, SQLite database နှင့် နမူနာအခန်း 101–112 ကို ပြင်ဆင်ထားပြီးဖြစ်သည်။ `.tools/` ထဲတွင် ဤ Mac အတွက် PHP နှင့် Composer ရှိသည်။ Git တွင် runtime၊ ဒေတာ၊ credentials နှင့် `.env` မပါဝင်ပါ။

VS Code Terminal မှ project folder တွင်:

```sh
./bin/motel motel:create-manager
./bin/motel serve --host=127.0.0.1 --port=8000
```

ပထမ command တွင် မန်နေဂျာအမည်၊ အီးမေးလ်၊ စကားဝှက် (အနည်းဆုံး ၁၂ လုံး) သတ်မှတ်ပါ။ Default login မရှိပါ။ Browser မှ http://127.0.0.1:8000 ကို ဖွင့်ပြီး သတ်မှတ်ခဲ့သော account ဖြင့် ဝင်ပါ။ မန်နေဂျာမှ ဝန်ထမ်းအကောင့်များ ထပ်ဖန်တီးနိုင်သည်။

VS Code → Terminal → Run Task တွင် **Motel: Create manager**, **Motel: Serve**, **Motel: Test** တို့လည်း အသုံးပြုနိုင်သည်။

## လုပ်ဆောင်ချက်များ

- အခန်းအခြေအနေ: အားလပ် / ဧည့်သည်ရှိ / သန့်ရှင်းရေးလို / ပြင်ဆင်နေ။
- ဧည့်သည်အမည်၊ ဖုန်း၊ ဝင်ချိန်၊ ထွက်ရန်အချိန်၊ မှတ်ချက်နှင့် အခန်းပေးခြင်း။ အမည်/ဖုန်းနှင့် ရှာနိုင်သည်။
- Check-in တွင် အခန်းကို occupied ပြောင်းသည်။ တစ်ခန်းတွင် active stay တစ်ခုသာရှိနိုင်သည်။ Transaction နှင့် unique database constraint ဖြင့် ကာကွယ်သည်။
- အခန်းခ၊ အပိုငွေ၊ ပေးငွေ၊ ကျန်ငွေ။ MMK ကိန်းပြည့်သာ လက်ခံသည်။ ငွေသား / ငွေလွှဲ / ကတ်။
- ကျန်ငွေရှင်းပြီးမှ Check-out လုပ်နိုင်သည်။ ပြီးပါက dirty ဖြစ်သည်။ **အခန်းသန့်ပြီး** တစ်ချက်နှိပ်၍ available ပြန်ပြောင်းသည်။
- နေ့စဉ်ဝင်ထွက်၊ လက်ခံရရှိငွေ၊ ငွေပေးနည်းအလိုက် စုစုပေါင်း၊ အခန်းအသုံးပြုမှုနှင့် ငွေမှတ်တမ်း။ ယနေ့ဆို လက်ရှိ၊ ယခင်ရက်ဆို နေ့ကုန်အသုံးပြုမှုကို ပြသည်။ လက်ရှိကျန်ငွေကို သီးခြားဖော်ပြသည်။
- Staff: ဧည့်သည်ဝင်ထွက်၊ ဧည့်သည်အချက်အလက်၊ ငွေစာရင်း၊ dirty အခန်းသန့်ပြီးမှတ်ခြင်း၊ နေ့စဉ်စာရင်း။
- Manager: Staff လုပ်ဆောင်ချက်များ၊ အခန်း/နှုန်းပြင်ခြင်း၊ maintenance သတ်မှတ်ခြင်း၊ ဝန်ထမ်းဖန်တီး/ပိတ်ခြင်း၊ role ပြောင်းခြင်း၊ audit log။
- Login/logout၊ password ပြောင်းခြင်းနှင့် ဒေတာပြောင်းလဲမှုများအတွက် ဝန်ထမ်းအမည်၊ အချိန်၊ before/after မှတ်တမ်း။ စကားဝှက်ကို audit ထဲမသိမ်းပါ။

အခန်းခကို **တည်းခိုမှုတစ်ခုလုံးအတွက်** check-in အချိန် သတ်မှတ်သည်။ UI တွင် တစ်ညနှုန်း × calendar nights (အနည်းဆုံး တစ်ည) အကြံပြုတွက်ချက်ပေးသည်။ အချိန်တိုးလျှင် room charge အလိုအလျောက် မပြောင်းပါ။ အပိုငွေတွင် အကြောင်းအရာနှင့် ထည့်ပါ။ ထွက်ပြီးသော မှတ်တမ်းနှင့် လက်ခံပြီးငွေများကို UI မှ ဖျက်ခြင်းမပြုနိုင်ပါ။

## အခြားစက်ပေါ်တွင် install လုပ်ရန်

PHP 8.3+ (SQLite/PDO, mbstring, intl, DOM/XML, curl extensions) နှင့် Composer လိုသည်။

```sh
composer install
cp .env.example .env
php artisan key:generate
# database/database.sqlite ဖိုင်အလွတ် ဖန်တီးပါ
# macOS / Linux:
touch database/database.sqlite
php artisan migrate
php artisan db:seed  # optional: sample rooms 101–112
php artisan motel:create-manager
php artisan serve
```

SQLite ကို အလွယ်တကူ စတင်အသုံးပြုနိုင်သည်။ MySQL အသုံးပြုလိုပါက `.env` တွင် DB_CONNECTION နှင့် DB_HOST/PORT/DATABASE/USERNAME/PASSWORD သတ်မှတ်ပြီး `php artisan migrate` လုပ်ပါ။ ဤစက်တွင် SQLite ဖြင့် စမ်းသပ်ထားသည်။

## စမ်းသပ်ရန်

```sh
./bin/motel test
.tools/php vendor/bin/pint --test
```

Tests သည် သီးခြား in-memory SQLite ကို အသုံးပြုသည်။ လက်ရှိမိုတယ်ဒေတာကို မပြောင်းပါ။ Authentication, roles, disabled accounts, check-in/out, occupied-room uniqueness, billing, overpayment, cleaning, audit, daily date boundaries နှင့် views တို့ကို စမ်းသပ်ထားသည်။

## Internal server အတွက်

Development server ကို localhost တွင်သာ ဖွင့်ထားသည်။ ဝန်ထမ်းများအတူသုံးရန် dedicated internal server မှ `public/` ကို web root အဖြစ် သတ်မှတ်ပါ။ `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, HTTPS နှင့် `SESSION_SECURE_COOKIE=true` သတ်မှတ်ပြီး database backup ပုံမှန်လုပ်ပါ။ Production တွင် real credentials နှင့် သီးခြား APP_KEY အသုံးပြုပါ။ Server စနစ်ပေါ်မူတည်၍ PHP-FPM/Nginx သို့မဟုတ် Apache တပ်ဆင်ရန် လိုသည်။
