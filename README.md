# Motel Management

မိုတယ်ဝန်ထမ်းသုံး internal software — Laravel 13 / PHP 8.4+ / Blade / SQLite။ မြန်မာစာ UI၊ MMK ငွေကြေး၊ Asia/Yangon အချိန်။ CSS နှင့် JavaScript ကို local မှ ဖတ်သည်။ Node/npm build မလိုပါ။

## Git မှ clone လုပ်ပြီး local တွင် run ရန်

PHP 8.4+ နှင့် Composer လိုအပ်သည်။ SQLite/PDO, mbstring, intl, DOM/XML နှင့် curl extensions ရှိရမည်။ ယခု Mac တွင် `.tools/` မှ PHP/Composer ကို အသုံးပြုနိုင်သည်။ အခြားစက်တွင် ကိုယ်ပိုင် PHP/Composer ကို install လုပ်ထားပါ။ `.tools/` သည် Git ထဲ မပါဝင်ပါ။

```sh
git clone --branch demo https://github.com/Pabohere/Motel-Management.git
cd Motel-Management
./bin/setup --sample-rooms
./bin/motel motel:create-manager
./bin/start
```

ပထမဆုံး မန်နေဂျာအမည်၊ အီးမေးလ်၊ စကားဝှက် (အနည်းဆုံး ၁၂ လုံး) သတ်မှတ်ပါ။ Default login မရှိပါ။ Browser မှ **http://127.0.0.1:8000** ကို ဖွင့်ပြီး သတ်မှတ်ခဲ့သော account ဖြင့် ဝင်ပါ။ မန်နေဂျာမှ ဝန်ထမ်းအကောင့်များ ထပ်ဖန်တီးနိုင်သည်။ Server ပိတ်ရန် Terminal တွင် `Ctrl+C` နှိပ်ပါ။ နောက်တစ်ကြိမ် ပြန်သုံးလျှင် `./bin/start` တစ်ခုတည်း run ပါ။

`./bin/setup` သည် dependencies, `.env`, encryption key နှင့် database tables ကို ပြင်ဆင်ပေးသည်။ ထပ် run လျှင် ရှိပြီးသား APP_KEY နှင့် ဒေတာကို ထိန်းထားသည်။ `--sample-rooms` ထည့်လျှင် နမူနာအခန်း 101–112 ထည့်ပေးသည်။ လက်ရှိအခန်းများ၏ နှုန်းနှင့် အခြေအနေကို မပြောင်းပါ။

ယခုစက်ပေါ် project ရှိပြီးသားဆို `git clone` မလိုပါ။ VS Code Terminal မှ project folder တွင် အထက်ပါ setup/account/start command များကို run ပါ။ VS Code → Terminal → Run Task တွင် **Motel: Setup local**, **Motel: Create manager**, **Motel: Serve**, **Motel: Test** ကိုလည်း သုံးနိုင်သည်။

ဒေတာနှင့် ဝန်ထမ်းအကောင့်များသည် `database/database.sqlite` ထဲတွင် သိမ်းထားပြီး စက်ပြန်ဖွင့်လည်း ကျန်ရှိသည်။ ဧည့်သည်အထောက်အထားဖိုင်များကို `storage/app/private/guest-identities` ထဲတွင် သိမ်းသည်။ `.env`, database, upload ဖိုင်များနှင့် runtime ဖိုင်များ Git ထဲ မပါဝင်ပါ။ Backup လုပ်လျှင် server ပိတ်ပြီး database ဖိုင်၊ `.env` နှင့် `storage/app/private/guest-identities` ကို သီးခြားသိမ်းပါ။ လက်ရှိ project တွင် update ဆွဲပြီးနောက် `./bin/motel migrate` တစ်ကြိမ် run ပါ။

Port 8000 ကို အခြား app သုံးနေပါက `MOTEL_PORT=8002 ./bin/start` ဖြင့် စတင်နိုင်သည်။ Local စက်ပေါ်တွင်သာ ဖွင့်ထားသည်။

## လုပ်ဆောင်ချက်များ

- အခန်းအခြေအနေ: အားလပ် / ဧည့်သည်ရှိ / သန့်ရှင်းရေးလို / ပြင်ဆင်နေ။
- ဧည့်သည်အမည်၊ ဖုန်း၊ ဝင်ချိန်၊ ထွက်ရန်အချိန်၊ မှတ်ချက်၊ အခန်းနှင့် မှတ်ပုံတင်/အထောက်အထားဖိုင် (JPG/PNG/WebP/PDF, 5 MB အထိ)။ အမည်/ဖုန်းနှင့် ရှာနိုင်သည်။ ဖိုင်ကို login ဝင်ထားသူသာ download လုပ်နိုင်သည်။
- Check-in တွင် အခန်းကို occupied ပြောင်းသည်။ တစ်ခန်းတွင် active stay တစ်ခုသာရှိနိုင်သည်။ Transaction နှင့် unique database constraint ဖြင့် ကာကွယ်သည်။
- အခန်းခ၊ အပိုငွေ၊ ပေးငွေ၊ ကျန်ငွေ။ MMK ကိန်းပြည့်သာ လက်ခံသည်။ ငွေသား / ငွေလွှဲ / ကတ်။
- ကျန်ငွေရှင်းပြီးမှ Check-out လုပ်နိုင်သည်။ ပြီးပါက dirty ဖြစ်သည်။ **အခန်းသန့်ပြီး** တစ်ချက်နှိပ်၍ available ပြန်ပြောင်းသည်။
- နေ့စဉ်ဝင်ထွက်၊ လက်ခံရရှိငွေ၊ ငွေပေးနည်းအလိုက် စုစုပေါင်း၊ အခန်းအသုံးပြုမှုနှင့် ငွေမှတ်တမ်း။ ယနေ့ဆို လက်ရှိ၊ ယခင်ရက်ဆို နေ့ကုန်အသုံးပြုမှုကို ပြသည်။ လက်ရှိကျန်ငွေကို သီးခြားဖော်ပြသည်။
- မန်နေဂျာအတွက် လရွေးကြည့်နိုင်သည့် ဝင်ငွေ/ထွက်ငွေ၊ အသားတင်လက်ကျန်၊ ငွေပေးနည်းနှင့် ထွက်ငွေအမျိုးအစား စာရင်းချုပ်။ CSV download သို့မဟုတ် Print/PDF ထုတ်နိုင်သည်။ ထွက်ငွေကို ရက်စွဲ၊ အမျိုးအစား၊ အကြောင်းအရာ၊ ပမာဏဖြင့် ထည့်နိုင်သည်။
- Staff: ဧည့်သည်ဝင်ထွက်၊ ဧည့်သည်အချက်အလက်၊ ငွေစာရင်း၊ dirty အခန်းသန့်ပြီးမှတ်ခြင်း၊ နေ့စဉ်စာရင်း။
- Manager: Staff လုပ်ဆောင်ချက်များ၊ အခန်း/နှုန်းပြင်ခြင်း၊ maintenance သတ်မှတ်ခြင်း၊ လစဉ်စာရင်းနှင့် ထွက်ငွေ၊ ဝန်ထမ်းဖန်တီး/ပိတ်ခြင်း၊ role ပြောင်းခြင်း၊ audit log။
- Login/logout၊ password ပြောင်းခြင်းနှင့် ဒေတာပြောင်းလဲမှုများအတွက် ဝန်ထမ်းအမည်၊ အချိန်၊ before/after မှတ်တမ်း။ စကားဝှက်ကို audit ထဲမသိမ်းပါ။

အခန်းခကို **တည်းခိုမှုတစ်ခုလုံးအတွက်** check-in အချိန် သတ်မှတ်သည်။ UI တွင် တစ်ညနှုန်း × calendar nights (အနည်းဆုံး တစ်ည) အကြံပြုတွက်ချက်ပေးသည်။ အချိန်တိုးလျှင် room charge အလိုအလျောက် မပြောင်းပါ။ အပိုငွေတွင် အကြောင်းအရာနှင့် ထည့်ပါ။ ထွက်ပြီးသော မှတ်တမ်းနှင့် လက်ခံပြီးငွေများကို UI မှ ဖျက်ခြင်းမပြုနိုင်ပါ။

## စမ်းသပ်ရန်

```sh
./bin/motel test
.tools/php vendor/bin/pint --test
```

Tests သည် သီးခြား in-memory SQLite ကို အသုံးပြုသည်။ လက်ရှိမိုတယ်ဒေတာကို မပြောင်းပါ။ Authentication, roles, disabled accounts, check-in/out, occupied-room uniqueness, billing, overpayment, cleaning, audit, daily date boundaries နှင့် views တို့ကို စမ်းသပ်ထားသည်။
