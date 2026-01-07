import asyncpg
from typing import List, Dict, Any, Optional
import logging
from config import Config

logger = logging.getLogger(__name__)

class Database:
    def __init__(self):
        self.pool = None
    
    async def connect(self):
        """Ma'lumotlar bazasiga ulanish"""
        try:
            self.pool = await asyncpg.create_pool(
                user=Config.DB_USER,
                password=Config.DB_PASSWORD,
                database=Config.DB_NAME,
                host=Config.DB_HOST,
                port=Config.DB_PORT
            )
            await self.create_tables()
            logger.info("Database connected successfully")
        except Exception as e:
            logger.error(f"Database connection error: {e}")
            raise
    
    async def disconnect(self):
        """Ulanishni uzish"""
        if self.pool:
            await self.pool.close()
    
    async def create_tables(self):
        """Jadvallarni yaratish"""
        sql = """
        -- Users jadvali
        CREATE TABLE IF NOT EXISTS users (
            id SERIAL PRIMARY KEY,
            user_id BIGINT UNIQUE NOT NULL,
            username VARCHAR(255),
            first_name VARCHAR(255),
            last_name VARCHAR(255),
            status VARCHAR(50) DEFAULT 'oddiy',
            balance DECIMAL(10,2) DEFAULT 0,
            referrals INTEGER DEFAULT 0,
            vip_expire DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        
        -- Anime ro'yxati
        CREATE TABLE IF NOT EXISTS anime (
            id SERIAL PRIMARY KEY,
            title VARCHAR(500) NOT NULL,
            thumbnail_id VARCHAR(500),
            episodes_count VARCHAR(100),
            country VARCHAR(100),
            language VARCHAR(100),
            year VARCHAR(100),
            genres TEXT,
            anime_type VARCHAR(100),
            search_count INTEGER DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        
        -- Anime qismlari
        CREATE TABLE IF NOT EXISTS anime_episodes (
            id SERIAL PRIMARY KEY,
            anime_id INTEGER REFERENCES anime(id),
            episode_number INTEGER NOT NULL,
            file_id VARCHAR(500) NOT NULL,
            file_type VARCHAR(20) DEFAULT 'video',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(anime_id, episode_number)
        );
        
        -- Kanalar
        CREATE TABLE IF NOT EXISTS channels (
            id SERIAL PRIMARY KEY,
            channel_id VARCHAR(100) NOT NULL,
            channel_name VARCHAR(255),
            channel_link VARCHAR(500),
            channel_type VARCHAR(50) DEFAULT 'mandatory'
        );
        
        -- VIP status
        CREATE TABLE IF NOT EXISTS vip_status (
            id SERIAL PRIMARY KEY,
            user_id BIGINT REFERENCES users(user_id),
            days INTEGER NOT NULL,
            expire_date DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        """
        
        async with self.pool.acquire() as conn:
            await conn.execute(sql)
    
    # User operations
    async def add_user(self, user_id: int, username: str, first_name: str, last_name: str = ""):
        """Yangi foydalanuvchi qo'shish"""
        sql = """
        INSERT INTO users (user_id, username, first_name, last_name)
        VALUES ($1, $2, $3, $4)
        ON CONFLICT (user_id) DO NOTHING
        RETURNING id
        """
        async with self.pool.acquire() as conn:
            return await conn.fetchval(sql, user_id, username, first_name, last_name)
    
    async def get_user(self, user_id: int) -> Optional[Dict]:
        """Foydalanuvchi ma'lumotlarini olish"""
        sql = "SELECT * FROM users WHERE user_id = $1"
        async with self.pool.acquire() as conn:
            return await conn.fetchrow(sql, user_id)
    
    # Anime operations
    async def add_anime(self, title: str, thumbnail_id: str, episodes_count: str, 
                       country: str, language: str, year: str, genres: str, anime_type: str) -> int:
        """Yangi anime qo'shish"""
        sql = """
        INSERT INTO anime (title, thumbnail_id, episodes_count, country, 
                          language, year, genres, anime_type)
        VALUES ($1, $2, $3, $4, $5, $6, $7, $8)
        RETURNING id
        """
        async with self.pool.acquire() as conn:
            result = await conn.fetchrow(sql, title, thumbnail_id, episodes_count, 
                                       country, language, year, genres, anime_type)
            return result['id']
    
    async def get_anime(self, anime_id: int) -> Optional[Dict]:
        """Anime ma'lumotlarini olish"""
        sql = "SELECT * FROM anime WHERE id = $1"
        async with self.pool.acquire() as conn:
            return await conn.fetchrow(sql, anime_id)
    
    async def search_anime(self, query: str, limit: int = 10) -> List[Dict]:
        """Animelarni qidirish"""
        sql = """
        SELECT * FROM anime 
        WHERE title ILIKE $1 OR genres ILIKE $1
        ORDER BY search_count DESC
        LIMIT $2
        """
        async with self.pool.acquire() as conn:
            return await conn.fetch(sql, f"%{query}%", limit)
    
    async def get_recent_anime(self, limit: int = 10) -> List[Dict]:
        """Oxirgi qo'shilgan animelar"""
        sql = "SELECT * FROM anime ORDER BY created_at DESC LIMIT $1"
        async with self.pool.acquire() as conn:
            return await conn.fetch(sql, limit)
    
    async def get_top_anime(self, limit: int = 10) -> List[Dict]:
        """Eng ko'p qidirilgan animelar"""
        sql = "SELECT * FROM anime ORDER BY search_count DESC LIMIT $1"
        async with self.pool.acquire() as conn:
            return await conn.fetch(sql, limit)
    
    # Episode operations
    async def add_episode(self, anime_id: int, episode_number: int, file_id: str):
        """Yangi qism qo'shish"""
        sql = """
        INSERT INTO anime_episodes (anime_id, episode_number, file_id)
        VALUES ($1, $2, $3)
        ON CONFLICT (anime_id, episode_number) DO UPDATE
        SET file_id = EXCLUDED.file_id
        """
        async with self.pool.acquire() as conn:
            await conn.execute(sql, anime_id, episode_number, file_id)
    
    async def get_episode(self, anime_id: int, episode_number: int) -> Optional[Dict]:
        """Qism ma'lumotlarini olish"""
        sql = "SELECT * FROM anime_episodes WHERE anime_id = $1 AND episode_number = $2"
        async with self.pool.acquire() as conn:
            return await conn.fetchrow(sql, anime_id, episode_number)
    
    async def get_anime_episodes(self, anime_id: int) -> List[Dict]:
        """Anime qismlarini olish"""
        sql = "SELECT * FROM anime_episodes WHERE anime_id = $1 ORDER BY episode_number"
        async with self.pool.acquire() as conn:
            return await conn.fetch(sql, anime_id)
    
    # VIP operations
    async def add_vip(self, user_id: int, days: int):
        """VIP qo'shish"""
        from datetime import datetime, timedelta
        expire_date = datetime.now() + timedelta(days=days)
        
        sql = """
        INSERT INTO vip_status (user_id, days, expire_date)
        VALUES ($1, $2, $3)
        ON CONFLICT (user_id) DO UPDATE
        SET days = vip_status.days + $2,
            expire_date = $3
        """
        async with self.pool.acquire() as conn:
            await conn.execute(sql, user_id, days, expire_date)
            await conn.execute("UPDATE users SET status = 'VIP' WHERE user_id = $1", user_id)
    
    # Admin operations
    async def get_all_users(self) -> List[Dict]:
        """Barcha foydalanuvchilar"""
        sql = "SELECT * FROM users ORDER BY created_at DESC"
        async with self.pool.acquire() as conn:
            return await conn.fetch(sql)
    
    async def get_statistics(self) -> Dict:
        """Statistika"""
        sql_users = "SELECT COUNT(*) FROM users"
        sql_anime = "SELECT COUNT(*) FROM anime"
        sql_episodes = "SELECT COUNT(*) FROM anime_episodes"
        sql_vip = "SELECT COUNT(*) FROM users WHERE status = 'VIP'"
        
        async with self.pool.acquire() as conn:
            total_users = await conn.fetchval(sql_users)
            total_anime = await conn.fetchval(sql_anime)
            total_episodes = await conn.fetchval(sql_episodes)
            total_vip = await conn.fetchval(sql_vip)
            
            return {
                'total_users': total_users,
                'total_anime': total_anime,
                'total_episodes': total_episodes,
                'total_vip': total_vip
            }