from aiogram import Router, F
from aiogram.types import Message, CallbackQuery
from aiogram.filters import Command, CommandStart
from aiogram.fsm.context import FSMContext
from aiogram.fsm.state import State, StatesGroup
import logging
from datetime import datetime

from database import Database
from keyboards import get_main_menu, get_admin_menu, get_search_keyboard, get_vip_keyboard, get_episode_keyboard
from config import Config

router = Router()
db = Database()
logger = logging.getLogger(__name__)

class AddAnime(StatesGroup):
    title = State()
    episodes = State()
    country = State()
    language = State()
    year = State()
    genre = State()
    anime_type = State()
    thumbnail = State()

# Start command
@router.message(CommandStart())
async def cmd_start(message: Message):
    user_id = message.from_user.id
    username = message.from_user.username or ""
    first_name = message.from_user.first_name or ""
    last_name = message.from_user.last_name or ""
    
    # Foydalanuvchini bazaga qo'shish
    await db.add_user(user_id, username, first_name, last_name)
    
    # Admin tekshirish
    if user_id in Config.ADMIN_IDS:
        await message.answer("👋 Admin paneliga xush kelibsiz!", reply_markup=get_admin_menu())
    else:
        await message.answer("👋 Anime botiga xush kelibsiz!", reply_markup=get_main_menu())

# Asosiy menyu
@router.message(F.text == "◀️ Orqaga")
async def cmd_back(message: Message):
    user_id = message.from_user.id
    
    if user_id in Config.ADMIN_IDS:
        await message.answer("Admin paneli", reply_markup=get_admin_menu())
    else:
        await message.answer("Asosiy menyu", reply_markup=get_main_menu())

# Anime qidirish
@router.message(F.text == "🔎 Anime izlash")
async def search_anime(message: Message):
    await message.answer("🔍 Qidiruv tipini tanlang:", reply_markup=get_search_keyboard())

# VIP bo'limi
@router.message(F.text == "💎 VIP")
async def vip_section(message: Message):
    user = await db.get_user(message.from_user.id)
    
    if user and user['status'] == 'VIP':
        # Agar VIP bo'lsa
        await message.answer("⭐ Siz VIP statusdasiz!", reply_markup=get_main_menu("VIP"))
    else:
        # VIP sotib olish
        await message.answer(
            "💎 VIP sotib olish\n\n"
            "VIP da qanday imkoniyatlar bor?\n"
            "• VIP kanal uchun 1 martalik havola\n"
            "• Hech qanday reklamasiz foydalanish\n"
            "• Majburiy obuna so'ralmaydi",
            reply_markup=get_vip_keyboard()
        )

# Hisobim
@router.message(F.text == "💰 Hisobim")
async def my_account(message: Message):
    user = await db.get_user(message.from_user.id)
    
    if user:
        text = f"👤 ID: <code>{user['user_id']}</code>\n"
        text += f"💰 Balans: {user['balance']} so'm\n"
        text += f"📊 Status: {user['status']}\n"
        
        if user['status'] == 'VIP':
            text += "⭐ VIP status faol"
        
        await message.answer(text, parse_mode="HTML")
    else:
        await message.answer("❌ Foydalanuvchi topilmadi")

# Callback query handler
@router.callback_query(F.data.startswith("vip_"))
async def vip_callback(callback: CallbackQuery):
    days = int(callback.data.split("_")[1])
    user_id = callback.from_user.id
    
    if days == 30:
        price = 25000
    elif days == 60:
        price = 50000
    else:  # 90 kun
        price = 75000
    
    user = await db.get_user(user_id)
    
    if user['balance'] >= price:
        # VIP qo'shish
        await db.add_vip(user_id, days)
        
        # Balansdan chiqarish
        await db.update_balance(user_id, -price)
        
        await callback.message.answer(f"✅ {days} kunlik VIP sotib olindi!")
        await callback.answer()
    else:
        await callback.answer("❌ Hisobingizda yetarli mablag' yo'q!", show_alert=True)

# Anime ko'rish
@router.callback_query(F.data.startswith("episode_"))
async def show_episode(callback: CallbackQuery):
    data = callback.data.split("_")
    anime_id = int(data[1])
    episode_num = int(data[2])
    
    # Episodni olish
    episode = await db.get_episode(anime_id, episode_num)
    anime = await db.get_anime(anime_id)
    
    if episode and anime:
        # Tugmalarni tayyorlash
        episodes = await db.get_anime_episodes(anime_id)
        total_episodes = len(episodes)
        
        keyboard = get_episode_keyboard(anime_id, episode_num, total_episodes)
        
        # Video yuborish
        await callback.message.answer_video(
            video=episode['file_id'],
            caption=f"🎬 {anime['title']}\n\n"
                   f"📀 {episode_num}-qism\n"
                   f"🎞 Jami: {total_episodes} qism",
            reply_markup=keyboard
        )
        await callback.answer()
    else:
        await callback.answer("❌ Qism topilmadi", show_alert=True)

# Admin panel
@router.message(F.text == "🗄 Boshqarish")
async def admin_panel(message: Message):
    if message.from_user.id in Config.ADMIN_IDS:
        await message.answer("👮 Admin paneli", reply_markup=get_admin_menu())
    else:
        await message.answer("❌ Ruxsat yo'q")

# Statistika
@router.message(F.text == "📊 Statistika")
async def statistics(message: Message):
    if message.from_user.id in Config.ADMIN_IDS:
        stats = await db.get_statistics()
        
        text = "📊 Bot statistika:\n\n"
        text += f"👥 Foydalanuvchilar: {stats['total_users']} ta\n"
        text += f"🎬 Animelar: {stats['total_anime']} ta\n"
        text += f"📀 Qismlar: {stats['total_episodes']} ta\n"
        text += f"⭐ VIPlar: {stats['total_vip']} ta"
        
        await message.answer(text)
    else:
        await message.answer("❌ Ruxsat yo'q")

# Anime qo'shish boshlash
@router.message(F.text == "🎥 Animelar sozlash")
async def anime_settings(message: Message):
    if message.from_user.id in Config.ADMIN_IDS:
        keyboard = InlineKeyboardMarkup(inline_keyboard=[
            [InlineKeyboardButton(text="➕ Anime qo'shish", callback_data="add_anime")],
            [InlineKeyboardButton(text="📥 Qism qo'shish", callback_data="add_episode")],
            [InlineKeyboardButton(text="📝 Anime tahrirlash", callback_data="edit_anime")]
        ])
        await message.answer("🎬 Anime sozlamalari:", reply_markup=keyboard)
    else:
        await message.answer("❌ Ruxsat yo'q")

@router.callback_query(F.data == "add_anime")
async def start_add_anime(callback: CallbackQuery, state: FSMContext):
    await state.set_state(AddAnime.title)
    await callback.message.answer("🎬 Anime nomini kiriting:")
    await callback.answer()

@router.message(AddAnime.title)
async def process_title(message: Message, state: FSMContext):
    await state.update_data(title=message.text)
    await state.set_state(AddAnime.episodes)
    await message.answer("🎥 Jami qismlar sonini kiriting:")

@router.message(AddAnime.episodes)
async def process_episodes(message: Message, state: FSMContext):
    await state.update_data(episodes=message.text)
    await state.set_state(AddAnime.country)
    await message.answer("🌍 Davlatini kiriting:")

@router.message(AddAnime.country)
async def process_country(message: Message, state: FSMContext):
    await state.update_data(country=message.text)
    await state.set_state(AddAnime.language)
    await message.answer("🗣 Tilini kiriting:")

@router.message(AddAnime.language)
async def process_language(message: Message, state: FSMContext):
    await state.update_data(language=message.text)
    await state.set_state(AddAnime.year)
    await message.answer("📅 Yilini kiriting:")

@router.message(AddAnime.year)
async def process_year(message: Message, state: FSMContext):
    await state.update_data(year=message.text)
    await state.set_state(AddAnime.genre)
    await message.answer("🎞 Janrini kiriting:")

@router.message(AddAnime.genre)
async def process_genre(message: Message, state: FSMContext):
    await state.update_data(genre=message.text)
    await state.set_state(AddAnime.anime_type)
    await message.answer("🎙️ Fandub nomini kiriting:")

@router.message(AddAnime.anime_type)
async def process_anime_type(message: Message, state: FSMContext):
    await state.update_data(anime_type=message.text)
    await state.set_state(AddAnime.thumbnail)
    await message.answer("🖼️ Rasm yoki video yuboring (60 soniyadan oshmasin):")

@router.message(AddAnime.thumbnail)
async def process_thumbnail(message: Message, state: FSMContext):
    if message.photo:
        file_id = message.photo[-1].file_id
    elif message.video and message.video.duration <= 60:
        file_id = message.video.file_id
    else:
        await message.answer("❌ Iltimos, rasm yoki 60 soniyadan oshmagan video yuboring!")
        return
    
    data = await state.get_data()
    
    # Anime qo'shish
    anime_id = await db.add_anime(
        title=data['title'],
        thumbnail_id=file_id,
        episodes_count=data['episodes'],
        country=data['country'],
        language=data['language'],
        year=data['year'],
        genres=data['genre'],
        anime_type=data['anime_type']
    )
    
    await message.answer(f"✅ Anime qo'shildi!\n\nKod: <code>{anime_id}</code>", parse_mode="HTML")
    await state.clear()