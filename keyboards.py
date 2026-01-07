from aiogram.types import ReplyKeyboardMarkup, KeyboardButton, InlineKeyboardMarkup, InlineKeyboardButton
from config import Config

def get_main_menu(user_status="oddiy"):
    """Asosiy menyu"""
    keyboard = [
        [KeyboardButton(text="🔎 Anime izlash")],
        [KeyboardButton(text="💎 VIP"), KeyboardButton(text="💰 Hisobim")],
        [KeyboardButton(text="➕ Pul kiritish"), KeyboardButton(text="📚 Qo'llanma")],
        [KeyboardButton(text="💵 Reklama va Homiylik")]
    ]
    
    if user_status == "VIP":
        keyboard[1][0] = KeyboardButton(text="⭐ VIP")
    
    return ReplyKeyboardMarkup(keyboard=keyboard, resize_keyboard=True)

def get_admin_menu():
    """Admin menyusi"""
    keyboard = [
        [KeyboardButton(text="*️⃣ Birlamchi sozlamalar")],
        [KeyboardButton(text="📊 Statistika"), KeyboardButton(text="✉ Xabar Yuborish")],
        [KeyboardButton(text="📬 Post tayyorlash")],
        [KeyboardButton(text="🎥 Animelar sozlash"), KeyboardButton(text="💳 Hamyonlar")],
        [KeyboardButton(text="🔎 Foydalanuvchini boshqarish")],
        [KeyboardButton(text="📢 Kanallar"), KeyboardButton(text="🎛 Tugmalar"), KeyboardButton(text="📃 Matnlar")],
        [KeyboardButton(text="📋 Adminlar"), KeyboardButton(text="🤖 Bot holati")],
        [KeyboardButton(text="◀️ Orqaga")]
    ]
    return ReplyKeyboardMarkup(keyboard=keyboard, resize_keyboard=True)

def get_search_keyboard():
    """Qidiruv menyusi"""
    keyboard = InlineKeyboardMarkup(inline_keyboard=[
        [
            InlineKeyboardButton(text="🏷 Anime nomi orqali", callback_data="search_by_name"),
            InlineKeyboardButton(text="⏱ So'ngi yuklanganlar", callback_data="last_uploads")
        ],
        [
            InlineKeyboardButton(text="💬 Janr orqali qidirish", callback_data="search_by_genre"),
            InlineKeyboardButton(text="📌 Kod orqali", callback_data="search_by_code")
        ],
        [
            InlineKeyboardButton(text="👁️ Eng ko'p ko'rilgan", callback_data="top_viewers"),
            InlineKeyboardButton(text="🖼️ Rasm orqali qidirish", callback_data="search_by_image")
        ],
        [
            InlineKeyboardButton(text="🌐 Web Animes", web_app={"url": f"{Config.WEBSITE_URL}/animes"}),
            InlineKeyboardButton(text="📚 Barcha animelar", callback_data="all_animes")
        ]
    ])
    return keyboard

def get_vip_keyboard():
    """VIP menyusi"""
    keyboard = InlineKeyboardMarkup(inline_keyboard=[
        [
            InlineKeyboardButton(text="30 kun - 25000 so'm", callback_data="vip_30"),
            InlineKeyboardButton(text="60 kun - 50000 so'm", callback_data="vip_60")
        ],
        [
            InlineKeyboardButton(text="90 kun - 75000 so'm", callback_data="vip_90")
        ]
    ])
    return keyboard

def get_episode_keyboard(anime_id, current_episode, total_episodes, is_admin=False):
    """Qismlar uchun tugmalar"""
    buttons = []
    
    # Oldingi va keyingi tugmalar
    if current_episode > 1:
        buttons.append(InlineKeyboardButton(text="⬅️ Oldingi", 
                                          callback_data=f"episode_{anime_id}_{current_episode-1}"))
    
    buttons.append(InlineKeyboardButton(text=f"{current_episode}/{total_episodes}", 
                                       callback_data="current"))
    
    if current_episode < total_episodes:
        buttons.append(InlineKeyboardButton(text="➡️ Keyingi", 
                                          callback_data=f"episode_{anime_id}_{current_episode+1}"))
    
    keyboard = [buttons]
    
    # Yuklab olish tugmasi
    keyboard.append([InlineKeyboardButton(text="📥 Yuklab olish", 
                                         callback_data=f"download_{anime_id}_{current_episode}")])
    
    # Admin uchun o'chirish tugmasi
    if is_admin:
        keyboard.append([InlineKeyboardButton(text="🗑 O'chirish", 
                                            callback_data=f"delete_{anime_id}_{current_episode}")])
    
    return InlineKeyboardMarkup(inline_keyboard=keyboard)