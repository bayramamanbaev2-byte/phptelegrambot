import logging
import asyncio
import sqlite3
from datetime import datetime
from aiogram import Bot, Dispatcher, types, F
from aiogram.filters import Command
from aiogram.types import Message, ReplyKeyboardMarkup, KeyboardButton

# SOZLAMALAR
TOKEN = "8551657001:AAEYgG3HHQiWMazm1SVJZpXKQ3ttAvIN3iI"
ADMINS = [7179662037, 2025400572]

bot = Bot(token=TOKEN)
dp = Dispatcher()

# BAZA BILAN ISHLASH
def init_db():
    conn = sqlite3.connect('anime_bot.db')
    cursor = conn.cursor()
    # Foydalanuvchilar jadvali
    cursor.execute('''CREATE TABLE IF NOT EXISTS users 
                      (user_id INTEGER PRIMARY KEY, status TEXT, refid TEXT, sana TEXT)''')
    # Animelar jadvali
    cursor.execute('''CREATE TABLE IF NOT EXISTS anime_datas 
                      (data_id INTEGER PRIMARY KEY AUTO_INCREMENT, anime_id TEXT, file_id TEXT, qism TEXT, sana TEXT)''')
    conn.commit()
    conn.close()

def add_user(user_id, refid=None):
    conn = sqlite3.connect('anime_bot.db')
    cursor = conn.cursor()
    cursor.execute("INSERT OR IGNORE INTO users (user_id, status, refid, sana) VALUES (?, ?, ?, ?)",
                   (user_id, 'active', refid, datetime.now().strftime("%Y-%m-%d %H:%M:%S")))
    conn.commit()
    conn.close()

# START BUYRUG'I
@dp.message(Command("start"))
async def cmd_start(message: Message):
    user_id = message.from_user.id
    refid = message.text.split()[1] if len(message.text.split()) > 1 else None
    add_user(user_id, refid)
    
    markup = ReplyKeyboardMarkup(
        keyboard=[[KeyboardButton(text="Animelar 🎞"), KeyboardButton(text="Kabinet 👤")]],
        resize_keyboard=True
    )
    await message.answer("Xush kelibsiz! Anime botimizga xush kelibsiz.", reply_markup=markup)

# ADMIN PANEL (SODDA VARIANT)
@dp.message(Command("panel"))
async def admin_panel(message: Message):
    if message.from_user.id in ADMINS:
        await message.answer("Admin panelga xush kelibsiz!")

# BOTNI ISHGA TUSHIRISH
async def main():
    init_db()
    logging.basicConfig(level=logging.INFO)
    print("Bot ishga tushdi...")
    await dp.start_polling(bot)

if __name__ == "__main__":
    asyncio.run(main())

