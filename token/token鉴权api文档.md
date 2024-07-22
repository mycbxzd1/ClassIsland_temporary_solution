<center><font size=8>API 文档</font></center>

## 注册用户

### 请求

- **URL:** `/?api=register`
- **方法:** GET
- **参数:**
  - `pwd=管理员密码`
  - `name=用户名`
  - `password=用户密码`
  - `value=用户权值` （可选，默认0）

### 响应

- **成功:** 
```json
  {
    "message": "User registered successfully"
  }
```
- **失败:** 
```json
  {
    "error": "错误信息"
  }
```

## 刷新Token

### 请求

- **URL:** `/?api=refresh`
- **方法:** GET
- **参数:**
  - `pwd=管理员密码`

### 响应

- **成功:** 
  ```json
  {
    "message": "Tokens refreshed for all users"
  }
  ```
- **失败:** 
  ```json
  {
    "error": "错误信息"
  }
  ```

## 获取用户Token

### 请求

- **URL:** `/?api=get`
- **方法:** GET
- **参数:**
  - `name=用户名`
  - `password=用户密码`

### 响应

- **成功:** 
  ```json
  {
    "token": "用户Token",
    "value": 用户权值
  }
  ```
- **失败:** 
  ```json
  {
    "error": "错误信息"
  }
  ```

## 验证Token是否有效

### 请求

- **URL:** `/?api=check`
- **方法:** GET
- **参数:**
  - `token=Token`

### 响应

- **token有效:** 
  
  ```json
  {
    "valid": true,
    "value": 用户权值
  }
  ```
- **token无效:** 
  
  ```json
  {
    "valid": false
  }
  ```

## 删除用户

### 请求

- **URL:** `/?api=delete`
- **方法:** GET
- **参数:**
  - `pwd=管理员密码`
  - `name=用户名`

### 响应

- **成功:** 
  ```json
  {
    "message": "User and associated token deleted successfully"
  }
  ```
- **失败:** 
  ```json
  {
    "error": "错误信息"
  }
  ```

## 列出所有用户

### 请求

- **URL:** `/?api=list`
- **方法:** GET
- **参数:**
  - `pwd=管理员密码`

### 响应

- **成功:** 
  ```json
  [
    {
      "name": "用户名",
      "password": "用户密码",
      "value": 用户权值
    },
    ...
  ]
  ```
- **失败:** 
  ```json
  {
    "error": "错误信息"
  }
  ```

## 列出所有Token

### 请求

- **URL:** `/?api=list_token`
- **方法:** GET
- **参数:**
  - `pwd=管理员密码`

### 响应

- **成功:** 
  ```json
  [
    {
      "token": "Token",
      "name": "用户名",
      "value": 用户权值
    },
    ...
  ]
  ```
- **失败:** 
  ```json
  {
    "error": "错误信息"
  }
  ```

## 修改用户信息

### 请求

- **URL:** `/?api=change`
- **方法:** GET
- **参数:**
  - `pwd=管理员密码`
  - `name=目标用户名`
  - `password=新密码` （可选,不填默认不修改）
  - `value=新权值` （可选,不填默认不修改）

### 响应

- **成功:** 
  ```json
  {
    "message": "User information updated successfully"
  }
  ```
- **失败:** 
  ```json
  {
    "error": "错误信息"
  }
  ```