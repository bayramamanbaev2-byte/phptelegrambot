# main.py - Asosiy bot fayli

import os
import logging
from telegram import Update
from telegram.ext import Application, CommandHandler, MessageHandler, CallbackQueryHandler, filters, ContextTypes
from database import db

# Log sozlash
logging.basicConfig(
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    level=logging.INFO
)
logger = logging.getLogger(__name__)

# Bot token
BOT_TOKEN = os.getenv('BOT_TOKEN', '8551657001:AAEFBkGrkDgpeW-U-Vvl_XBqfz4uCdTSf3M')

async def start(update: Update, context: ContextTypes.DEFAULT_TYPE):
    """Start komandasi"""
    user_id = update.effective_user.id
    
    # Foydalanuvchini bazaga qo'shish
    db.add_user(user_id)
    
    await update.message.reply_text(
        "✨ Botga xush kelibsiz!",
        parse_mode='HTML'
    )

async def error_handler(update: object, context: ContextTypes.DEFAULT_TYPE):
    """Xatolarni qayta ishlash"""
    logger.error(f"Xato: {context.error}")
    
    if update and hasattr(update, 'effective_user'):
        await update.effective_user.send_message(
            "❌ Botda texnik muammo yuz berdi. Iltimos, keyinroq urinib ko'ring."
        )

def main():
    """Asosiy funksiya"""
    # Botni yaratish
    application = Application.builder().token(BOT_TOKEN).build()
    
    # Handlers
    application.add_handler(CommandHandler("start", start))
    
    # Xatolarni qayta ishlash
    application.add_error_handler(error_handler)
    
    # Botni ishga tushirish
    application.run_polling(allowed_updates=Update.ALL_TYPES)

if __name__ == "__main__":
    # Database statistikasini ko'rsatish
    stats = db.get_statistics()
    logger.info(f"Database statistikasi: {stats}")
    
    # Botni ishga tushirish
    main()
