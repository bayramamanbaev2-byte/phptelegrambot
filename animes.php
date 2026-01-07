import asyncio
import logging
import sqlite3
from datetime import datetime
from aiogram import Bot, Dispatcher, types, F
from aiogram.filters import Command
from aiogram.types import ReplyKeyboardMarkup, KeyboardButton, InlineKeyboardMarkup, InlineKeyboardButton

# --- SOZLAMALAR ---
TOKEN = "8551657001:AAHSG0veXc6s2-27a_dp7wp3KsTDS80kH3A"
ADMINS = [7179662037, 2025400572]

bot = Bot(token=TOKEN)
dp = Dispatcher()

# --- MA'LUMOTLAR BAZASI ---
def init_db():
    conn = sqlite3.connect('anime.db')
    c = conn.cursor()
    # Foydalanuvchilar jadvali
    c.execute('''CREATE TABLE IF NOT EXISTS users 
                 (user_id INTEGER PRIMARY KEY, refid TEXT, sana TEXT)''')
    # Animelar jadvali
    c.execute('''CREATE TABLE IF NOT EXISTS anime_datas 
                 (id INTEGER PRIMARY KEY AUTO_INCREMENT, anime_id TEXT, file_id TEXT, qism TEXT)''')
    conn.commit()
    conn.close()

# --- KLAVIATURALAR ---
main_menu = ReplyKeyboardMarkup(
    keyboard=[
        [KeyboardButton(text="Animelar 🎞"), KeyboardButton(text="Kabinet 👤")],
        [KeyboardButton(text="Qidiruv 🔍")]
    ],
    resize_keyboard=True
)

# --- HANDLERLAR ---
@dp.message(Command("start"))
async def start_cmd(message: types.Message):
    user_id = message.from_user.id
    ref_id = message.text.split()[1] if len(message.text.split()) > 1 else None
    
    conn = sqlite3.connect('anime.db')
    c = conn.cursor()
    c.execute("INSERT OR IGNORE INTO users (user_id, refid, sana) VALUES (?, ?, ?)", 
              (user_id, ref_id, datetime.now().strftime("%Y-%m-%d")))
    conn.commit()
    conn.close()
    
    await message.answer("Xush kelibsiz! Anime botimizga xush kelibsiz.", reply_markup=main_menu)

@dp.message(F.text == "Kabinet 👤")
async def kabinet(message: types.Message):
    user_id = message.from_user.id
    await message.answer(f"Sizning ID: {user_id}\nHolat: Faol ✅")

@dp.message(F.text == "Animelar 🎞")
async def list_animes(message: types.Message):
    # Bu yerda WebApp havolasini berishingiz mumkin (yuklagan animes.php o'rniga)
    await message.answer("Animelarni ko'rish uchun saytimizga kiring yoki qidiruvdan foydalaning.")

# --- ADMIN PANEL ---
@dp.message(Command("panel"))
async def admin_panel(message: types.Message):
    if message.from_user.id in ADMINS:
        await message.answer("Admin panelga xush kelibsiz, xo'jayin!")

async def main():
    init_db()
    logging.basicConfig(level=logging.INFO)
    await dp.start_polling(bot)

if __name__ == "__main__":
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        print("Bot to'xtatildi")
