import logging
import os
import json
import re
import time
import mysql.connector
from datetime import datetime
from dotenv import load_dotenv
from telegram import Update, InlineKeyboardButton, InlineKeyboardMarkup, WebAppInfo, KeyboardButton, ReplyKeyboardMarkup
from telegram.ext import Application, CommandHandler, MessageHandler, CallbackQueryHandler, ContextTypes, filters
from telegram.constants import ParseMode
import asyncio

# Log sozlash
logging.basicConfig(
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    level=logging.INFO
)
logger = logging.getLogger(__name__)

# Environment variables
load_dotenv()
BOT_TOKEN = os.getenv('BOT_TOKEN', '8551657001:AAEFBkGrkDgpeW-U-Vvl_XBqfz4uCdTSf3M')
ADMIN_ID = int(os.getenv('ADMIN_ID', '7179662037'))

# MySQL connection
def get_db_connection():
    return mysql.connector.connect(
        host="localhost",
        user="uztopanime",
        password="uztopanime123",
        database="uztopanime"
    )

# Bot funksiyalari
class AnimeBot:
    def __init__(self):
        self.connect = get_db_connection()
        self.cursor = self.connect.cursor(dictionary=True)
        
        # Fayl yollari
        self.admin_dir = "admin"
        self.step_dir = "step"
        self.text_dir = "matn"
        self.button_dir = "tugma"
        self.system_dir = "tizim"
        
        # Kerakli papkalarni yaratish
        self.create_directories()
        
    def create_directories(self):
        """Kerakli papkalarni yaratish"""
        directories = [self.admin_dir, self.step_dir, self.text_dir, 
                      self.button_dir, self.system_dir]
        for directory in directories:
            if not os.path.exists(directory):
                os.makedirs(directory)
    
    def read_file(self, file_path):
        """Fayldan o'qish"""
        if os.path.exists(file_path):
            with open(file_path, 'r', encoding='utf-8') as f:
                return f.read()
        return ""
    
    def write_file(self, file_path, content):
        """Faylga yozish"""
        with open(file_path, 'w', encoding='utf-8') as f:
            f.write(content)
    
    def append_file(self, file_path, content):
        """Faylga qo'shish"""
        with open(file_path, 'a', encoding='utf-8') as f:
            f.write(content)
    
    def delete_file(self, file_path):
        """Faylni o'chirish"""
        if os.path.exists(file_path):
            os.remove(file_path)
    
    def get_admins(self):
        """Adminlarni olish"""
        admins_content = self.read_file(f"{self.admin_dir}/admins.txt")
        admins = [int(admin.strip()) for admin in admins_content.split('\n') if admin.strip()]
        admins.extend([ADMIN_ID, 2025400572])
        return admins
    
    def is_admin(self, user_id):
        """Admin tekshirish"""
        admins = self.get_admins()
        return user_id in admins
    
    async def bot_send_message(self, chat_id, text, reply_markup=None, parse_mode=ParseMode.HTML):
        """Xabar yuborish"""
        from telegram import Bot
        bot = Bot(token=BOT_TOKEN)
        
        try:
            await bot.send_message(
                chat_id=chat_id,
                text=text,
                parse_mode=parse_mode,
                reply_markup=reply_markup,
                disable_web_page_preview=True
            )
        except Exception as e:
            logger.error(f"Xabar yuborishda xato: {e}")
    
    async def bot_edit_message(self, chat_id, message_id, text, reply_markup=None):
        """Xabarni tahrirlash"""
        from telegram import Bot
        bot = Bot(token=BOT_TOKEN)
        
        try:
            await bot.edit_message_text(
                chat_id=chat_id,
                message_id=message_id,
                text=text,
                parse_mode=ParseMode.HTML,
                reply_markup=reply_markup,
                disable_web_page_preview=True
            )
        except Exception as e:
            logger.error(f"Xabarni tahrirlashda xato: {e}")
    
    async def bot_delete_message(self, chat_id, message_id):
        """Xabarni o'chirish"""
        from telegram import Bot
        bot = Bot(token=BOT_TOKEN)
        
        try:
            await bot.delete_message(chat_id=chat_id, message_id=message_id)
        except Exception as e:
            logger.error(f"Xabarni o'chirishda xato: {e}")
    
    async def bot_send_video(self, chat_id, video, caption, reply_markup=None):
        """Video yuborish"""
        from telegram import Bot
        bot = Bot(token=BOT_TOKEN)
        
        try:
            await bot.send_video(
                chat_id=chat_id,
                video=video,
                caption=caption,
                parse_mode=ParseMode.HTML,
                reply_markup=reply_markup
            )
        except Exception as e:
            logger.error(f"Video yuborishda xato: {e}")
    
    async def bot_send_photo(self, chat_id, photo, caption, reply_markup=None):
        """Rasm yuborish"""
        from telegram import Bot
        bot = Bot(token=BOT_TOKEN)
        
        try:
            await bot.send_photo(
                chat_id=chat_id,
                photo=photo,
                caption=caption,
                parse_mode=ParseMode.HTML,
                reply_markup=reply_markup
            )
        except Exception as e:
            logger.error(f"Rasm yuborishda xato: {e}")
    
    async def check_subscription(self, user_id, callback_data=None):
        """Obunani tekshirish"""
        try:
            self.cursor.execute("SELECT channelId, channelType, channelLink FROM channels")
            channels = self.cursor.fetchall()
            
            if not channels:
                return True
            
            not_subs = 0
            buttons = []
            
            for channel in channels:
                channel_id = channel['channelId']
                channel_link = channel['channelLink']
                channel_type = channel['channelType']
                
                if channel_type == "request":
                    self.cursor.execute(
                        "SELECT * FROM joinRequests WHERE channelId = %s AND userId = %s",
                        (channel_id, user_id)
                    )
                    
                    if self.cursor.rowcount == 0:
                        not_subs += 1
                        buttons.append([
                            InlineKeyboardButton(
                                f"📨 So'rov yuborish ({not_subs})",
                                url=f"https://t.me/{BOT_TOKEN.split(':')[0]}?start=joinreq_{channel_id}"
                            )
                        ])
                else:
                    # Bu yerda telegram API orqali obunani tekshirish kerak
                    # Hozircha osonlik uchun true qaytaramiz
                    not_subs += 1
                    buttons.append([
                        InlineKeyboardButton(
                            f"Kanal {not_subs}",
                            url=channel_link
                        )
                    ])
            
            if not_subs > 0:
                insta = self.read_file(f"{self.admin_dir}/instagram.txt")
                youtube = self.read_file(f"{self.admin_dir}/youtube.txt")
                
                if insta:
                    buttons.append([InlineKeyboardButton("📸 Instagram", url=insta)])
                elif youtube:
                    buttons.append([InlineKeyboardButton("📺 YouTube", url=youtube)])
                
                callback = f"chack={callback_data}" if callback_data else "panel"
                buttons.append([
                    InlineKeyboardButton("✅ Tekshirish", callback_data=callback)
                ])
                
                await self.bot_send_message(
                    user_id,
                    "<b>Botdan foydalanish uchun quyidagi kanallarga obuna bo'ling yoki so'rov yuboring❗️</b>",
                    InlineKeyboardMarkup(buttons)
                )
                return False
            
            return True
            
        except Exception as e:
            logger.error(f"Obunani tekshirishda xato: {e}")
            return True
    
    async def process_anime(self, chat_id, anime_id):
        """Animani ko'rsatish"""
        try:
            self.cursor.execute("SELECT * FROM animelar WHERE id = %s", (anime_id,))
            anime = self.cursor.fetchone()
            
            if not anime:
                await self.bot_send_message(chat_id, "❗ Noto'g'ri ID kiritildi.")
                return
            
            file_id = anime['rams']
            first_char = file_id[0].upper() if file_id else 'P'
            
            # Ko'rishlar sonini oshirish
            self.cursor.execute(
                "UPDATE animelar SET qidiruv = qidiruv + 1 WHERE id = %s",
                (anime_id,)
            )
            self.connect.commit()
            
            anime_channel = self.read_file(f"{self.admin_dir}/anime_kanal.txt")
            content_protection = self.read_file(f"{self.system_dir}/content.txt") == "true"
            
            caption = f"""<b>🎬 Nomi: {anime['nom']}</b>

🎥 Qismi: {anime['qismi']}
🌍 Davlati: {anime['davlat']}
🇺🇿 Tili: {anime['tili']}
📆 Yili: {anime['yili']}
🎞 Janri: {anime['janri']}

🔍 Qidirishlar soni: {anime['qidiruv'] + 1}

🍿 {anime_channel}"""
            
            keyboard = [[
                InlineKeyboardButton("📥 Yuklab olish", callback_data=f"yuklanolish={anime_id}=1")
            ]]
            
            if first_char == 'B':
                await self.bot_send_video(
                    chat_id,
                    file_id,
                    caption,
                    InlineKeyboardMarkup(keyboard)
                )
            else:
                await self.bot_send_photo(
                    chat_id,
                    file_id,
                    caption,
                    InlineKeyboardMarkup(keyboard)
                )
                
        except Exception as e:
            logger.error(f"Animani ko'rsatishda xato: {e}")
            await self.bot_send_message(chat_id, "❌ Ma'lumot topilmadi.")
    
    async def handle_start(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Start komandasi"""
        chat_id = update.effective_chat.id
        user_id = update.effective_user.id
        name = update.effective_user.first_name
        
        # Foydalanuvchini bazaga qo'shish
        try:
            self.cursor.execute(
                "SELECT * FROM user_id WHERE user_id = %s",
                (user_id,)
            )
            if self.cursor.rowcount == 0:
                sana = datetime.now().strftime("%d.%m.%Y")
                self.cursor.execute(
                    "INSERT INTO user_id(user_id, status, sana) VALUES (%s, %s, %s)",
                    (user_id, 'Oddiy', sana)
                )
                
            self.cursor.execute(
                "SELECT * FROM kabinet WHERE user_id = %s",
                (user_id,)
            )
            if self.cursor.rowcount == 0:
                self.cursor.execute(
                    "INSERT INTO kabinet(user_id, pul, pul2, odam, ban) VALUES (%s, %s, %s, %s, %s)",
                    (user_id, 0, 0, 0, 'unban')
                )
            
            self.connect.commit()
            
        except Exception as e:
            logger.error(f"Bazaga foydalanuvchi qo'shishda xato: {e}")
        
        # Start matnini o'qish
        start_text = self.read_file(f"{self.text_dir}/start.txt")
        if not start_text:
            start_text = "✨"
        
        # Klaviatura yaratish
        keyboard = self.create_main_keyboard(user_id)
        
        await self.bot_send_message(
            chat_id,
            start_text,
            reply_markup=ReplyKeyboardMarkup(keyboard, resize_keyboard=True)
        )
    
    def create_main_keyboard(self, user_id):
        """Asosiy klaviaturani yaratish"""
        key1 = self.read_file(f"{self.button_dir}/key1.txt") or "🔎 Anime izlash"
        key2 = self.read_file(f"{self.button_dir}/key2.txt") or "💎 VIP"
        key3 = self.read_file(f"{self.button_dir}/key3.txt") or "💰 Hisobim"
        key4 = self.read_file(f"{self.button_dir}/key4.txt") or "➕ Pul kiritish"
        key5 = self.read_file(f"{self.button_dir}/key5.txt") or "📚 Qo'llanma"
        key6 = self.read_file(f"{self.button_dir}/key6.txt") or "💵 Reklama va Homiylik"
        
        keyboard = [
            [KeyboardButton(key1)],
            [KeyboardButton(key2), KeyboardButton(key3)],
            [KeyboardButton(key4), KeyboardButton(key5)],
            [KeyboardButton(key6)]
        ]
        
        if self.is_admin(user_id):
            keyboard.append([KeyboardButton("🗄 Boshqarish")])
        
        return keyboard
    
    async def handle_anime_search(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Anime qidirish"""
        chat_id = update.effective_chat.id
        
        # Obunani tekshirish
        if not await self.check_subscription(chat_id):
            return
        
        keyboard = [
            [
                InlineKeyboardButton("🏷Anime nomi orqali", callback_data="searchByName"),
                InlineKeyboardButton("⏱ So'ngi yuklanganlar", callback_data="lastUploads")
            ],
            [InlineKeyboardButton("💬Janr orqali qidirish", callback_data="searchByGenre")],
            [
                InlineKeyboardButton("📌Kod orqali", callback_data="searchByCode"),
                InlineKeyboardButton("👁️ Eng ko'p ko'rilgan", callback_data="topViewers")
            ],
            [InlineKeyboardButton("🖼️Rasm orqali qidirish", callback_data="searchByImage")],
            [
                InlineKeyboardButton("🌐 Web Animes", web_app=WebAppInfo(url="https://yourdomain.com/animes.php")),
                InlineKeyboardButton("🌐 ORG Coin", web_app=WebAppInfo(url="https://boltayevrahmatillo42.uztan.ga/Channel/index.php"))
            ],
            [InlineKeyboardButton("📚Barcha animelar", callback_data="allAnimes")]
        ]
        
        await self.bot_send_message(
            chat_id,
            "<b>🔍Qidiruv tipini tanlang :</b>",
            InlineKeyboardMarkup(keyboard)
        )
    
    async def handle_vip(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """VIP bo'limi"""
        chat_id = update.effective_chat.id
        
        # Obunani tekshirish
        if not await self.check_subscription(chat_id):
            return
        
        # Foydalanuvchi statusini olish
        self.cursor.execute("SELECT status FROM user_id WHERE user_id = %s", (chat_id,))
        user = self.cursor.fetchone()
        status = user['status'] if user else 'Oddiy'
        
        if status == "Oddiy":
            narx = self.read_file(f"{self.admin_dir}/vip.txt") or "25000"
            valyuta = self.read_file(f"{self.admin_dir}/valyuta.txt") or "so'm"
            
            keyboard = [
                [
                    InlineKeyboardButton(f"30 kun - {narx} {valyuta}", callback_data="shop=30"),
                    InlineKeyboardButton(f"60 kun - {int(narx)*2} {valyuta}", callback_data="shop=60"),
                    InlineKeyboardButton(f"90 kun - {int(narx)*3} {valyuta}", callback_data="shop=90")
                ]
            ]
            
            await self.bot_send_message(
                chat_id,
                f"""<b>VIP'ga ulanish

VIPda qanday imkoniyatlar bor?
• VIP kanal uchun 1martalik havola beriladi
• Hech qanday reklamalarsiz botdan foydalanasiz
• Majburiy obunalik so'ralmaydi</b>

VIP haqida batafsil Qo'llanma bo'limidan olishiz mumkin!""",
                InlineKeyboardMarkup(keyboard)
            )
        else:
            # VIP muddatini olish
            self.cursor.execute("SELECT kun FROM status WHERE user_id = %s", (chat_id,))
            vip_info = self.cursor.fetchone()
            aktiv_kun = vip_info['kun'] if vip_info else 0
            
            expire = datetime.now().strftime("%d.%m.%Y")  # Bu joyni to'g'irlash kerak
            keyboard = [[InlineKeyboardButton("🗓️ Uzaytirish", callback_data="uzaytirish")]]
            
            await self.bot_send_message(
                chat_id,
                f"<b>Siz VIP sotib olgansiz!</b>\n\n⏳ Amal qilish muddati {expire} gacha",
                InlineKeyboardMarkup(keyboard)
            )
    
    async def handle_balance(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Hisobni ko'rsatish"""
        chat_id = update.effective_chat.id
        
        # Obunani tekshirish
        if not await self.check_subscription(chat_id):
            return
        
        # Balansni olish
        self.cursor.execute("SELECT pul FROM kabinet WHERE user_id = %s", (chat_id,))
        balance = self.cursor.fetchone()
        pul = balance['pul'] if balance else 0
        valyuta = self.read_file(f"{self.admin_dir}/valyuta.txt") or "so'm"
        
        await self.bot_send_message(
            chat_id,
            f"#ID: <code>{chat_id}</code>\nBalans: {pul} {valyuta}"
        )
    
    async def handle_callback_query(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Callback querylarni qayta ishlash"""
        query = update.callback_query
        await query.answer()
        
        data = query.data
        chat_id = query.message.chat_id
        message_id = query.message.message_id
        
        if data == "close":
            await self.bot_delete_message(chat_id, message_id)
        
        elif data == "searchByName":
            await query.edit_message_text(
                "<b>Anime nomini yuboring:</b>",
                parse_mode=ParseMode.HTML
            )
        
        elif data == "searchByCode":
            await query.edit_message_text(
                "<b>📌 Anime kodini kiriting:</b>",
                parse_mode=ParseMode.HTML
            )
            self.write_file(f"{self.step_dir}/{chat_id}.step", "searchByCode")
        
        elif data.startswith("loadAnime="):
            anime_id = data.split("=")[1]
            await self.bot_delete_message(chat_id, message_id)
            await self.process_anime(chat_id, anime_id)
        
        elif data.startswith("yuklanolish="):
            await self.handle_download(chat_id, message_id, data)
        
        elif data.startswith("shop="):
            await self.handle_vip_purchase(chat_id, message_id, data)
        
        elif data == "uzaytirish":
            narx = self.read_file(f"{self.admin_dir}/vip.txt") or "25000"
            valyuta = self.read_file(f"{self.admin_dir}/valyuta.txt") or "so'm"
            
            keyboard = [
                [
                    InlineKeyboardButton(f"30 kun - {narx} {valyuta}", callback_data="shop=30"),
                    InlineKeyboardButton(f"60 kun - {int(narx)*2} {valyuta}", callback_data="shop=60"),
                    InlineKeyboardButton(f"90 kun - {int(narx)*3} {valyuta}", callback_data="shop=90")
                ]
            ]
            
            await query.edit_message_text(
                "<b>❗ Obunani necha kunga uzaytirmoqchisiz?</b>",
                parse_mode=ParseMode.HTML,
                reply_markup=InlineKeyboardMarkup(keyboard)
            )
        
        elif data.startswith("chack="):
            await self.handle_check_subscription(chat_id, message_id, data)
    
    async def handle_download(self, chat_id, message_id, data):
        """Yuklab olish bo'limi"""
        parts = data.split("=")
        anime_id = int(parts[1])
        episode_number = int(parts[2]) if len(parts) > 2 else 1
        
        # Qismni olish
        self.cursor.execute(
            "SELECT * FROM anime_datas WHERE id = %s AND qism = %s",
            (anime_id, episode_number)
        )
        episode = self.cursor.fetchone()
        
        if not episode:
            await self.bot_send_message(chat_id, "❌ Qism topilmadi.")
            return
        
        # Anime nomini olish
        self.cursor.execute("SELECT nom FROM animelar WHERE id = %s", (anime_id,))
        anime = self.cursor.fetchone()
        anime_name = anime['nom'] if anime else "Anime"
        
        # 25 ta qism uchun tugmalar
        offset = ((episode_number - 1) // 25) * 25
        self.cursor.execute(
            "SELECT qism FROM anime_datas WHERE id = %s LIMIT %s, 25",
            (anime_id, offset)
        )
        episodes = self.cursor.fetchall()
        
        buttons = []
        for ep in episodes:
            qism = ep['qism']
            if qism == episode_number:
                buttons.append(InlineKeyboardButton(f"[📀] - {qism}", callback_data="null"))
            else:
                buttons.append(InlineKeyboardButton(
                    str(qism), 
                    callback_data=f"yuklanolish={anime_id}={qism}={episode_number}"
                ))
        
        # Tugmalarni guruhlash
        keyboard = [buttons[i:i+4] for i in range(0, len(buttons), 4)]
        
        # Admin uchun o'chirish tugmasi
        if self.is_admin(chat_id):
            keyboard.append([
                InlineKeyboardButton(
                    f"🗑 {episode_number}-qismni o'chirish",
                    callback_data=f"deleteEpisode={anime_id}={episode_number}=1"
                )
            ])
        
        # Navigatsiya tugmalari
        keyboard.append([
            InlineKeyboardButton("⬅️ Oldingi", callback_data=f"pagenation={anime_id}={episode_number}=back"),
            InlineKeyboardButton("❌ Yopish", callback_data="close"),
            InlineKeyboardButton("➡️ Keyingi", callback_data=f"pagenation={anime_id}={episode_number}=next")
        ])
        
        # Video yuborish
        content_protection = self.read_file(f"{self.system_dir}/content.txt") == "true"
        
        await self.bot_send_video(
            chat_id,
            episode['file_id'],
            f"<b>{anime_name}</b>\n\n{episode_number}-qism",
            InlineKeyboardMarkup(keyboard)
        )
    
    async def handle_vip_purchase(self, chat_id, message_id, data):
        """VIP sotib olish"""
        days = int(data.split("=")[1])
        
        # Narxni hisoblash
        narx_text = self.read_file(f"{self.admin_dir}/vip.txt") or "25000"
        base_price = int(narx_text)
        price_per_day = base_price / 30
        total_price = price_per_day * days
        
        # Balansni tekshirish
        self.cursor.execute("SELECT pul, status FROM kabinet WHERE user_id = %s", (chat_id,))
        kabinet = self.cursor.fetchone()
        balance = kabinet['pul'] if kabinet else 0
        
        self.cursor.execute("SELECT status FROM user_id WHERE user_id = %s", (chat_id,))
        user = self.cursor.fetchone()
        status = user['status'] if user else 'Oddiy'
        
        if balance >= total_price:
            if status == "Oddiy":
                date = datetime.now().strftime("%d")
                # VIP ga qo'shish
                self.cursor.execute(
                    "INSERT INTO status (user_id, kun, date) VALUES (%s, %s, %s)",
                    (chat_id, days, date)
                )
                self.cursor.execute(
                    "UPDATE user_id SET status = 'VIP' WHERE user_id = %s",
                    (chat_id,)
                )
            else:
                # VIP ni uzaytirish
                self.cursor.execute("SELECT kun FROM status WHERE user_id = %s", (chat_id,))
                vip = self.cursor.fetchone()
                current_days = vip['kun'] if vip else 0
                new_days = current_days + days
                self.cursor.execute(
                    "UPDATE status SET kun = %s WHERE user_id = %s",
                    (new_days, chat_id)
                )
            
            # Balansni yangilash
            new_balance = balance - total_price
            self.cursor.execute(
                "UPDATE kabinet SET pul = %s WHERE user_id = %s",
                (new_balance, chat_id)
            )
            self.connect.commit()
            
            # Adminlarga xabar
            admins = self.get_admins()
            for admin_id in admins:
                await self.bot_send_message(
                    admin_id,
                    f"<a href='tg://user?id={chat_id}'>Foydalanuvchi</a> {days} kunlik obuna sotib oldi!"
                )
            
            await self.bot_edit_message(
                chat_id,
                message_id,
                "<b>💎 VIP - statusga muvaffaqiyatli o'tdingiz.</b>" if status == "Oddiy" 
                else "<b>💎 VIP - statusni muvaffaqiyatli uzaytirdingiz.</b>"
            )
        else:
            await query.answer(
                "Hisobingizda yetarli mablag' mavjud emas!",
                show_alert=True
            )
    
    async def handle_check_subscription(self, chat_id, message_id, data):
        """Obunani tekshirish"""
        anime_id = data.split("=")[1]
        
        # Progress bar
        progress_messages = [
            "<b>⏳ Tekshirilmoqda... 0%</b>\n░░░░░░",
            "<b>⏳ Tekshirilmoqda... 15%</b>\n█░░░░░",
            "<b>⏳ Tekshirilmoqda... 30%</b>\n██░░░░",
            "<b>⏳ Tekshirilmoqda... 45%</b>\n███░░░",
            "<b>⏳ Tekshirilmoqda... 60%</b>\n████░░",
            "<b>⏳ Tekshirilmoqda... 75%</b>\n█████░",
            "<b>⏳ Tekshirilmoqda... 90%</b>\n██████"
        ]
        
        for progress in progress_messages:
            await self.bot_edit_message(chat_id, message_id, progress)
            await asyncio.sleep(0.4)
        
        await self.bot_delete_message(chat_id, message_id)
        
        # Obunani tekshirish
        if await self.check_subscription(chat_id, anime_id):
            await self.process_anime(chat_id, anime_id)
        else:
            await self.bot_send_message(
                chat_id,
                "⚠ Obuna aniqlanmadi. Iltimos, kanallarga obuna bo'ling va qayta urinib ko'ring."
            )
    
    async def handle_text_message(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Matnli xabarlarni qayta ishlash"""
        message = update.message
        text = message.text
        chat_id = message.chat.id
        
        # Step faylini o'qish
        step_file = f"{self.step_dir}/{chat_id}.step"
        step = self.read_file(step_file) if os.path.exists(step_file) else ""
        
        if text == "/start" or text == "◀️ Orqaga":
            await self.handle_start(update, context)
            if os.path.exists(step_file):
                os.remove(step_file)
        
        elif text == "🔎 Anime izlash":
            await self.handle_anime_search(update, context)
        
        elif text == "💎 VIP":
            await self.handle_vip(update, context)
        
        elif text == "💰 Hisobim":
            await self.handle_balance(update, context)
        
        elif step == "searchByCode":
            # Kod orqali qidirish
            if text.isdigit():
                await self.process_anime(chat_id, int(text))
            else:
                await self.bot_send_message(
                    chat_id,
                    f"<b>[ {text} ] kodiga tegishli anime topilmadi😔</b>\n\n• Boshqa Kod yuboring"
                )
        
        elif self.is_admin(chat_id):
            await self.handle_admin_commands(update, context, text, step)
        
        else:
            # Anime nomi orqali qidirish
            await self.search_by_name(chat_id, text)
    
    async def search_by_name(self, chat_id, query):
        """Anime nomi orqali qidirish"""
        try:
            self.cursor.execute(
                "SELECT * FROM animelar WHERE nom LIKE %s LIMIT 10",
                (f"%{query}%",)
            )
            results = self.cursor.fetchall()
            
            if not results:
                return  # Natija yo'q holatida hech narsa qilmaymiz
            
            buttons = []
            for i, anime in enumerate(results, 1):
                buttons.append([
                    InlineKeyboardButton(
                        f"{i}. {anime['nom']}",
                        callback_data=f"loadAnime={anime['id']}"
                    )
                ])
            
            await self.bot_send_message(
                chat_id,
                "<b>⬇️ Qidiruv natijalari:</b>",
                InlineKeyboardMarkup(buttons)
            )
            
        except Exception as e:
            logger.error(f"Qidirishda xato: {e}")
    
    async def handle_admin_commands(self, update: Update, context: ContextTypes.DEFAULT_TYPE, text: str, step: str):
        """Admin komandalarini qayta ishlash"""
        chat_id = update.message.chat.id
        
        if text == "🗄 Boshqarish":
            await self.show_admin_panel(chat_id)
        
        elif text == "📊 Statistika":
            await self.show_statistics(chat_id)
        
        elif text == "✉ Xabar Yuborish":
            await self.prepare_broadcast(chat_id)
        
        elif text == "📬 Post tayyorlash":
            await self.prepare_post(chat_id)
        
        elif text == "🎥 Animelar sozlash":
            await self.show_anime_settings(chat_id)
        
        elif text == "💳 Hamyonlar":
            await self.show_payment_settings(chat_id)
        
        elif text == "🔎 Foydalanuvchini boshqarish":
            await self.prepare_user_management(chat_id)
        
        elif text == "📢 Kanallar":
            await self.show_channel_settings(chat_id)
        
        elif text == "🎛 Tugmalar":
            await self.show_button_settings(chat_id)
        
        elif text == "📃 Matnlar":
            await self.show_text_settings(chat_id)
        
        elif text == "📋 Adminlar":
            await self.show_admin_settings(chat_id)
        
        elif text == "🤖 Bot holati":
            await self.show_bot_status(chat_id)
        
        elif text == "*️⃣ Birlamchi sozlamalar":
            await self.show_basic_settings(chat_id)
        
        elif step.startswith("add-anime"):
            await self.handle_add_anime(update, context, text, step)
    
    async def show_admin_panel(self, chat_id):
        """Admin panelini ko'rsatish"""
        keyboard = [
            [KeyboardButton("*️⃣ Birlamchi sozlamalar")],
            [KeyboardButton("📊 Statistika"), KeyboardButton("✉ Xabar Yuborish")],
            [KeyboardButton("📬 Post tayyorlash")],
            [KeyboardButton("🎥 Animelar sozlash"), KeyboardButton("💳 Hamyonlar")],
            [KeyboardButton("🔎 Foydalanuvchini boshqarish")],
            [KeyboardButton("📢 Kanallar"), KeyboardButton("🎛 Tugmalar"), KeyboardButton("📃 Matnlar")],
            [KeyboardButton("📋 Adminlar"), KeyboardButton("🤖 Bot holati")],
            [KeyboardButton("◀️ Orqaga")]
        ]
        
        await self.bot_send_message(
            chat_id,
            "<b>Admin paneliga xush kelibsiz!</b>",
            reply_markup=ReplyKeyboardMarkup(keyboard, resize_keyboard=True)
        )
    
    async def show_statistics(self, chat_id):
        """Statistikani ko'rsatish"""
        try:
            self.cursor.execute("SELECT COUNT(*) as total FROM kabinet")
            stat = self.cursor.fetchone()
            total_users = stat['total'] if stat else 0
            
            # System load (Linux uchun)
            import psutil
            load = psutil.getloadavg()[0]
            
            keyboard = [[InlineKeyboardButton("Orqaga", callback_data="boshqarish")]]
            
            await self.bot_send_message(
                chat_id,
                f"""💡 <b>O'rtacha yuklanish:</b> <code>{load:.2f}</code>

👥 <b>Foydalanuvchilar:</b> {total_users} ta""",
                InlineKeyboardMarkup(keyboard)
            )
            
        except Exception as e:
            logger.error(f"Statistika ko'rsatishda xato: {e}")
            await self.bot_send_message(chat_id, "❌ Statistika olishda xatolik!")
    
    async def prepare_broadcast(self, chat_id):
        """Xabar yuborishni tayyorlash"""
        # Xabar yuborish holatini tekshirish
        self.cursor.execute("SELECT * FROM send")
        if self.cursor.rowcount > 0:
            await self.bot_send_message(
                chat_id,
                "<b>📑 Hozirda botda xabar yuborish jarayoni davom etmoqda. "
                "Yangi xabar yuborish uchun eski yuborilayotgan xabar "
                "barcha foydalanuvchilarga yuborilishini kuting!</b>",
                reply_markup=ReplyKeyboardMarkup(self.create_main_keyboard
