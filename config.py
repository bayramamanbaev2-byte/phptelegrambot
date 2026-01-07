import os
from dotenv import load_dotenv

load_dotenv()

class Config:
    BOT_TOKEN = os.getenv("BOT_TOKEN", "8551657001:AAHSG0veXc6s2-27a_dp7wp3KsTDS80kH3A")
    ADMIN_IDS = [7179662037, 2025400572]
    
    # Database
    DB_HOST = os.getenv("DB_HOST", "localhost")
    DB_NAME = os.getenv("DB_NAME", "anime_bot")
    DB_USER = os.getenv("DB_USER", "postgres")
    DB_PASSWORD = os.getenv("DB_PASSWORD", "password")
    DB_PORT = os.getenv("DB_PORT", "5432")
    
    # Web server
    WEBAPP_HOST = "0.0.0.0"
    WEBAPP_PORT = int(os.getenv("PORT", 5000))
    
    # Website URL
    WEBSITE_URL = os.getenv("WEBSITE_URL", "https://your-render-app.onrender.com")