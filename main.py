import asyncio
import logging
from aiogram import Bot, Dispatcher
from aiogram.enums import ParseMode
from aiogram.client.default import DefaultBotProperties

from config import Config
from database import Database
from handlers import router

# Logging sozlash
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

async def main():
    # Bot yaratish
    bot = Bot(token=Config.BOT_TOKEN, default=DefaultBotProperties(parse_mode=ParseMode.HTML))
    dp = Dispatcher()
    
    # Database ga ulanish
    db = Database()
    await db.connect()
    
    # Routerlarni qo'shish
    dp.include_router(router)
    
    # Webhook uchun (Render)
    from aiogram.webhook.aiohttp_server import SimpleRequestHandler, setup_application
    from aiohttp import web
    
    if Config.WEBHOOK_HOST:
        # Webhook sozlash
        await bot.set_webhook(Config.WEBHOOK_URL)
        
        # Server yaratish
        app = web.Application()
        webhook_requests_handler = SimpleRequestHandler(
            dispatcher=dp,
            bot=bot,
        )
        webhook_requests_handler.register(app, path=Config.WEBHOOK_PATH)
        setup_application(app, dp, bot=bot)
        
        logger.info(f"Webhook server starting on {Config.WEBAPP_HOST}:{Config.WEBAPP_PORT}")
        await web._run_app(app, host=Config.WEBAPP_HOST, port=Config.WEBAPP_PORT)
    else:
        # Polling rejimi
        logger.info("Starting bot in polling mode...")
        await bot.delete_webhook(drop_pending_updates=True)
        await dp.start_polling(bot)

if __name__ == "__main__":
    asyncio.run(main())