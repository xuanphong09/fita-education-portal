HỌC VIỆN NÔNG NGHIỆP VIỆT NAM

**KHOA CÔNG NGHỆ THÔNG TIN**

**KHOÁ LUẬN TỐT NGHIỆP**

**ĐỀ TÀI:**

> **XÂY DỰNG HỆ THỐNG CỔNG THÔNG TIN TÍCH HỢP VÀ QUẢN LÝ LỘ TRÌNH ĐÀO TẠO CHO KHOA CÔNG NGHỆ THÔNG TIN**

| **SINH VIÊN THỰC HIỆN** | **: PHẠM XUÂN PHONG** |
|---|---|
| **KHÓA** | **: 67** |
| **NGÀNH** | **: CÔNG NGHỆ THÔNG TIN** |
| **CHUYÊN NGÀNH** | **: CÔNG NGHỆ PHẦN MỀM** |
| **GIẢNG VIÊN HƯỚNG DẪN** | **: THS. NGÔ CÔNG THẮNG** |

**HÀ NỘI - 2026**

# MỤC LỤC

1. **Chương 1. Tổng quan đề tài**  
2. **Chương 2. Cơ sở lý thuyết và công nghệ**  
3. **Chương 3. Phân tích và thiết kế hệ thống**  
4. **Chương 4. Xây dựng và triển khai hệ thống**  
5. **Chương 5. Kiểm thử và đánh giá**  
6. **Kết luận và hướng phát triển**  
7. **Tài liệu tham khảo**

---

# CHƯƠNG 1. TỔNG QUAN ĐỀ TÀI

## 1.1. Tên đề tài

Đề tài nghiên cứu: **Xây dựng hệ thống cổng thông tin tích hợp và quản lý lộ trình đào tạo cho khoa Công nghệ Thông tin**.

Đây là một đề tài có tính ứng dụng cao trong bối cảnh các đơn vị đào tạo đại học đang đẩy mạnh chuyển đổi số, chuẩn hóa quy trình truyền thông học thuật và số hóa dữ liệu chương trình đào tạo. Hệ thống được xây dựng nhằm đáp ứng đồng thời hai nhóm nhu cầu: một là nhu cầu công bố thông tin, tin tức, hình ảnh, giảng viên và nội dung giới thiệu của khoa; hai là nhu cầu quản lý cấu trúc đào tạo gồm chương trình, học kỳ, học phần, tiên quyết, tương đương và đề cương môn học.

## 1.2. Đặt vấn đề

Trong hoạt động của một khoa chuyên ngành, cổng thông tin đóng vai trò là kênh giao tiếp chính thức giữa nhà trường với sinh viên, giảng viên, phụ huynh và các đối tượng quan tâm bên ngoài. Nếu hệ thống thông tin được tổ chức rời rạc, mỗi nội dung được lưu ở một nơi khác nhau hoặc cập nhật bởi nhiều nhóm người dùng mà thiếu cơ chế kiểm soát, thì việc công bố thông tin sẽ dễ xảy ra chậm trễ, sai lệch hoặc không thống nhất.

Đối với khoa Công nghệ Thông tin, nhu cầu này càng trở nên rõ rệt hơn. Khoa không chỉ cần một website để đăng tin tức, mà còn cần một nền tảng có khả năng tổ chức nội dung theo chủ đề, phân quyền biên tập, phê duyệt bài viết, hiển thị giảng viên, thư viện ảnh, thông báo học thuật, đồng thời mô hình hóa chương trình đào tạo theo từng khóa tuyển sinh và từng học kỳ. Sinh viên cần tra cứu lộ trình học tập; giảng viên cần cập nhật thông tin chuyên môn; cán bộ quản trị cần kiểm soát nội dung và chất lượng công bố.

Từ thực tế đó, việc xây dựng một hệ thống cổng thông tin tích hợp và quản lý lộ trình đào tạo là một yêu cầu hợp lý, có giá trị thực tiễn rõ ràng. Hệ thống sau khi hoàn thiện không chỉ hỗ trợ truyền thông của khoa mà còn trở thành công cụ phục vụ quản lý đào tạo, tư vấn học tập và truy xuất tài liệu học phần. Đây cũng là cơ sở để nâng cao hiệu quả điều hành và thể hiện hình ảnh chuyên nghiệp của đơn vị trong môi trường số.

## 1.3. Mục tiêu đề tài

Mục tiêu tổng quát của đề tài là xây dựng một hệ thống web hoàn chỉnh, thống nhất giữa cổng thông tin công khai và khu vực quản trị đào tạo, có khả năng vận hành ổn định, mở rộng tốt và phù hợp với nhu cầu thực tế của khoa Công nghệ Thông tin.

Các mục tiêu cụ thể bao gồm:

| Mã mục tiêu | Nội dung |
|---|---|
| MT01 | Xây dựng cổng thông tin công khai để hiển thị giới thiệu, tin tức, bài viết, giảng viên, thư viện ảnh, liên hệ và các nội dung giao diện. |
| MT02 | Thiết kế cơ chế xác thực người dùng, thiết lập mật khẩu, đăng nhập/đăng xuất và phân quyền theo vai trò, quyền chức năng. |
| MT03 | Xây dựng quy trình biên tập và duyệt bài viết theo trạng thái, có lưu lịch sử xử lý và thông báo qua email. |
| MT04 | Quản lý chương trình đào tạo theo ngành, chuyên ngành, khóa, học kỳ, học phần, học phần tiên quyết và học phần tương đương. |
| MT05 | Cho phép tra cứu và xem trước đề cương môn học bằng cơ chế an toàn, có kiểm soát truy cập. |
| MT06 | Tổ chức mã nguồn theo kiến trúc rõ ràng, thuận tiện bảo trì, kiểm thử và mở rộng trong tương lai. |

Ngoài các mục tiêu chức năng, đề tài còn hướng tới mục tiêu kỹ thuật quan trọng là xây dựng được một quy trình quản lý nội dung có truy vết. Mỗi thao tác biên tập, phê duyệt, từ chối hoặc xuất bản phải được ghi nhận đầy đủ để đảm bảo tính minh bạch và trách nhiệm trong vận hành.

## 1.4. Phạm vi nghiên cứu

Phạm vi nghiên cứu của đề tài tập trung vào hệ thống cổng thông tin cấp khoa, không đi sâu vào quản lý đào tạo cấp trường hoặc tích hợp với các hệ thống nghiệp vụ bên ngoài.

Về mặt chức năng, hệ thống bao gồm các nhóm nội dung chính sau:

- Quản lý nội dung công khai: bài viết, danh mục, banner, album ảnh, trang giới thiệu, liên hệ và giảng viên.
- Quản lý đào tạo: ngành, chuyên ngành, bộ môn, chương trình đào tạo, học kỳ, học phần, tiên quyết và tương đương.
- Quản trị người dùng: xác thực, phân quyền, vai trò, thiết lập mật khẩu và thông báo qua email.
- Tra cứu học thuật: xem bài viết, tìm kiếm, tra cứu chương trình đào tạo và mở đề cương môn học.

Về công nghệ, đề tài sử dụng Laravel, Livewire, MySQL cùng các thư viện hỗ trợ phân quyền, đa ngôn ngữ, gửi mail và quản lý hàng đợi. Các chức năng đồng bộ thời gian thực với hệ thống khác, phân tích dữ liệu nâng cao hoặc báo cáo thống kê chuyên sâu chưa được triển khai trong phạm vi hiện tại.

## 1.5. Phương pháp thực hiện

Đề tài được thực hiện theo phương pháp kết hợp giữa phân tích hệ thống thông tin và phát triển phần mềm hướng đối tượng. Quy trình nghiên cứu bao gồm các bước sau:

1. Khảo sát nhu cầu thực tế của khoa và đối chiếu với cấu trúc nghiệp vụ của hệ thống.
2. Phân tích tác nhân, luồng nghiệp vụ và ràng buộc dữ liệu.
3. Thiết kế kiến trúc ứng dụng, mô hình dữ liệu và cơ chế phân quyền.
4. Xây dựng các module chức năng theo từng phân hệ.
5. Kiểm thử các chức năng trọng yếu bằng kiểm thử tính năng.
6. Đánh giá kết quả, nhận diện hạn chế và đề xuất hướng phát triển.

Trong quá trình thực hiện, đề tài áp dụng cách tiếp cận theo module, tức là mỗi phân hệ như bài viết, đào tạo, media hay giảng viên được xây dựng như một thành phần tương đối độc lập nhưng vẫn thống nhất bởi cơ chế xác thực, phân quyền và chuẩn dữ liệu chung. Cách làm này giúp hệ thống dễ kiểm thử, dễ bảo trì và có khả năng phát triển lâu dài.

## 1.6. Bố cục báo cáo

Báo cáo được tổ chức thành 5 chương chính. Chương 1 trình bày tổng quan đề tài; Chương 2 trình bày cơ sở lý thuyết và công nghệ; Chương 3 phân tích và thiết kế hệ thống; Chương 4 trình bày xây dựng và triển khai hệ thống; Chương 5 trình bày kiểm thử và đánh giá kết quả. Cuối cùng là phần kết luận và hướng phát triển.

Mỗi chương trong báo cáo có mối liên kết chặt chẽ, bảo đảm tính logic của một khóa luận đại học hoàn chỉnh: chương 1 trả lời câu hỏi vì sao thực hiện đề tài; chương 2 trình bày dựa trên nền tảng nào; chương 3 mô tả hệ thống cần gì và thiết kế ra sao; chương 4 giải thích cách xây dựng hệ thống; chương 5 cho thấy hệ thống hoạt động có đạt yêu cầu hay không.

---

# CHƯƠNG 2. CƠ SỞ LÝ THUYẾT VÀ CÔNG NGHỆ

## 2.1. Cơ sở lý thuyết về cổng thông tin tích hợp

Cổng thông tin tích hợp là một hệ thống thông tin tập trung cho phép cung cấp nhiều loại nội dung và dịch vụ trên cùng một giao diện thống nhất. Khác với website giới thiệu đơn thuần, cổng thông tin thường có nhiều nhóm người dùng, nhiều luồng thao tác và nhiều loại dữ liệu khác nhau. Hệ thống không chỉ hiển thị thông tin mà còn hỗ trợ tìm kiếm, phân quyền, quản lý nội dung, duyệt bài và liên kết tài liệu.

Trong môi trường giáo dục đại học, cổng thông tin có vai trò đặc biệt quan trọng vì đây là nơi công bố các thông báo chính thức, giới thiệu hoạt động đào tạo, hiển thị đội ngũ giảng viên và hỗ trợ người học tra cứu thông tin học thuật. Một cổng thông tin tốt cần đảm bảo tính rõ ràng, nhất quán, dễ truy cập, có khả năng mở rộng và có cơ chế kiểm soát nội dung để bảo đảm độ tin cậy.

## 2.2. Cơ sở lý thuyết về quản lý lộ trình đào tạo

Quản lý lộ trình đào tạo là việc mô hình hóa chương trình học thành một cấu trúc có thứ bậc và có ràng buộc nghiệp vụ rõ ràng. Từ góc độ dữ liệu, một chương trình đào tạo thường bao gồm ngành, chuyên ngành, khóa tuyển sinh, học kỳ, danh sách học phần, các quan hệ tiên quyết và các quan hệ tương đương. Từ góc độ sử dụng, người học cần biết môn nào học trước, môn nào học sau, môn nào bắt buộc, môn nào tương đương và tổng số tín chỉ của từng học kỳ.

Nếu dữ liệu đào tạo được tổ chức không chặt chẽ, việc công bố chương trình học sẽ thiếu đồng bộ giữa các khóa và gây khó khăn cho sinh viên khi tra cứu. Vì vậy, hệ thống cần chuẩn hóa dữ liệu đào tạo theo cấu trúc rõ ràng, vừa phục vụ lưu trữ vừa hỗ trợ hiển thị lộ trình học tập một cách trực quan.

## 2.3. Công nghệ Laravel

Laravel là một framework PHP hiện đại, được thiết kế theo kiến trúc MVC và cung cấp nhiều công cụ hữu ích cho phát triển ứng dụng web như routing, middleware, Eloquent ORM, migration, validation, queue, mail và notification. Trong đề tài này, Laravel đóng vai trò là nền tảng lõi để xây dựng toàn bộ hệ thống.

Lý do chọn Laravel gồm:

- Có cấu trúc rõ ràng, dễ tổ chức mã nguồn và bảo trì.
- Tích hợp tốt với cơ sở dữ liệu quan hệ thông qua Eloquent ORM.
- Có hệ sinh thái hỗ trợ mạnh cho xác thực, phân quyền, mail, queue và test.
- Phù hợp với các hệ thống có nhiều phân hệ quản trị và công khai.

Ngoài các ưu điểm trên, Laravel còn cho phép tổ chức logic nghiệp vụ thành service, model và component riêng biệt. Điều này rất phù hợp với hệ thống có nhiều quy trình như duyệt bài viết, gửi thông báo, xử lý tài liệu và tra cứu đào tạo.

## 2.4. Công nghệ Livewire

Livewire cho phép xây dựng giao diện tương tác cao ngay trong Laravel mà không cần phát triển một ứng dụng SPA độc lập. Với Livewire, các thao tác như tạo, sửa, lọc, phân trang, xác nhận và duyệt dữ liệu có thể được thực hiện trực tiếp trên component server-side, đồng thời vẫn đảm bảo trải nghiệm người dùng tốt.

Trong hệ thống cổng thông tin của khoa, Livewire đặc biệt phù hợp với các màn hình quản trị như bài viết, danh mục, học phần, học kỳ, banner và album ảnh. Ưu điểm của Livewire là giảm đáng kể độ phức tạp khi triển khai giao diện, đặc biệt với các màn hình có nhiều biểu mẫu và nhiều điều kiện nghiệp vụ.

## 2.5. MySQL và mô hình quan hệ

MySQL là hệ quản trị cơ sở dữ liệu quan hệ được sử dụng để lưu trữ toàn bộ dữ liệu của hệ thống. Trong bài toán này, dữ liệu có tính liên kết rất cao: bài viết gắn với danh mục; chương trình đào tạo gắn với ngành, học kỳ và học phần; học phần có quan hệ tiên quyết và tương đương; người dùng gắn với vai trò và quyền hạn.

Mô hình quan hệ là lựa chọn phù hợp vì cho phép đảm bảo tính toàn vẹn dữ liệu, hỗ trợ truy vấn hiệu quả và dễ dàng biểu diễn các quan hệ một-nhiều, nhiều-nhiều và tự liên kết. Cùng với đó, các chỉ mục, khóa ngoại và ràng buộc duy nhất đóng vai trò quan trọng trong việc bảo vệ dữ liệu ngay từ tầng cơ sở dữ liệu.

## 2.6. Các thư viện hỗ trợ chính

Hệ thống sử dụng một số thư viện hỗ trợ quan trọng sau:

| Thư viện | Vai trò |
|---|---|
| Spatie Permission | Quản lý vai trò và quyền theo chức năng, phục vụ phân quyền chi tiết. |
| Spatie Translatable | Hỗ trợ lưu trữ và hiển thị dữ liệu đa ngôn ngữ. |
| Laravel Mail/Queue | Gửi email và đưa tác vụ nặng vào hàng đợi xử lý nền. |
| Laravel Scout | Hỗ trợ tìm kiếm người dùng và dữ liệu theo chỉ mục tìm kiếm. |
| Eloquent ORM | Mô hình hóa quan hệ dữ liệu và triển khai truy vấn nghiệp vụ. |

Sự kết hợp giữa các thư viện này giúp hệ thống vừa đáp ứng đúng yêu cầu nghiệp vụ vừa giữ được mã nguồn gọn gàng, dễ kiểm thử và dễ mở rộng.

## 2.7. Định hướng kiến trúc hệ thống

Hệ thống được tổ chức theo mô hình ba lớp logic:

- **Lớp giao diện**: hiển thị nội dung công khai và giao diện quản trị bằng Blade/Livewire.
- **Lớp nghiệp vụ**: xử lý xác thực, duyệt bài, gửi thông báo, phân tích dữ liệu và kiểm tra ràng buộc.
- **Lớp dữ liệu**: lưu trữ thông tin trong MySQL và truy cập thông qua Eloquent.

Cách tổ chức này phù hợp với các hệ thống cổng thông tin có nhiều thành phần chức năng, vì nó bảo đảm nguyên tắc tách biệt trách nhiệm và giúp hệ thống dễ bảo trì trong dài hạn.

---

# CHƯƠNG 3. PHÂN TÍCH VÀ THIẾT KẾ HỆ THỐNG

## 3.1. Phân tích tổng quan bài toán

Hệ thống được thiết kế để phục vụ đồng thời hai bài toán nghiệp vụ: truyền thông nội dung của khoa và quản lý lộ trình đào tạo. Đây là hai nhóm dữ liệu khác nhau về bản chất nhưng có mối liên hệ chặt chẽ trong thực tiễn.

Nhóm dữ liệu truyền thông gồm tin tức, bài viết, banner, ảnh và các trang giới thiệu. Nhóm dữ liệu đào tạo gồm ngành, chuyên ngành, học phần, học kỳ, chương trình đào tạo, tiên quyết, tương đương và đề cương môn học. Hệ thống cần bảo đảm rằng hai nhóm dữ liệu này được quản lý thống nhất nhưng vẫn tách biệt về quy trình xử lý.

## 3.2. Tác nhân hệ thống

| Mã tác nhân | Tác nhân | Vai trò |
|---|---|---|
| ACT01 | Khách truy cập | Xem nội dung công khai, tìm kiếm, tra cứu chương trình đào tạo và đề cương. |
| ACT02 | Sinh viên | Đăng nhập, xem thông tin cá nhân, tra cứu lộ trình học và tài liệu môn học. |
| ACT03 | Giảng viên | Quản lý hồ sơ giảng viên, tham gia biên tập hoặc duyệt nội dung theo quyền. |
| ACT04 | Biên tập viên | Tạo bài viết, cập nhật nội dung và gửi bài chờ duyệt. |
| ACT05 | Người duyệt | Kiểm tra nội dung, phê duyệt hoặc từ chối bài viết. |
| ACT06 | Quản trị viên | Quản lý toàn bộ hệ thống, người dùng, quyền hạn, đào tạo và media. |

## 3.3. Yêu cầu chức năng

| Mã | Nhóm chức năng | Mô tả yêu cầu |
|---|---|---|
| FR01 | Cổng thông tin công khai | Hệ thống phải hiển thị trang chủ, giới thiệu, liên hệ, bài viết, giảng viên và thư viện ảnh. |
| FR02 | Tìm kiếm và tra cứu | Hệ thống phải cho phép tìm kiếm bài viết, giảng viên và thông tin đào tạo. |
| FR03 | Xác thực người dùng | Hệ thống phải hỗ trợ đăng nhập, đăng xuất, quên mật khẩu và thiết lập mật khẩu ban đầu. |
| FR04 | Phân quyền | Hệ thống phải kiểm soát truy cập theo vai trò và quyền chức năng. |
| FR05 | Quản lý bài viết | Hệ thống phải cho phép tạo, sửa, duyệt, từ chối, xuất bản và lưu lịch sử bài viết. |
| FR06 | Quản lý đào tạo | Hệ thống phải quản lý ngành, chương trình đào tạo, học kỳ, học phần, tiên quyết và tương đương. |
| FR07 | Đề cương môn học | Hệ thống phải cho phép xem trước hoặc stream đề cương môn học an toàn. |
| FR08 | Quản lý media | Hệ thống phải hỗ trợ banner, album ảnh và hình ảnh truyền thông. |
| FR09 | Quản lý trang nội dung | Hệ thống phải cho phép cấu hình các trang giới thiệu, footer hoặc nội dung tĩnh. |
| FR10 | Thông báo | Hệ thống phải gửi thông báo qua email khi có thay đổi trạng thái bài viết hoặc sự kiện nghiệp vụ. |

## 3.4. Yêu cầu phi chức năng

| Mã | Nhóm yêu cầu | Nội dung |
|---|---|---|
| NFR01 | Hiệu năng | Trang web phải phản hồi nhanh, các danh sách phải phân trang và truy vấn được tối ưu. |
| NFR02 | Bảo mật | Chức năng quản trị phải được bảo vệ bởi xác thực, phân quyền và middleware. |
| NFR03 | Toàn vẹn dữ liệu | Dữ liệu phải tuân thủ khóa chính, khóa ngoại, ràng buộc duy nhất và các quy tắc nghiệp vụ. |
| NFR04 | Khả dụng | Giao diện phải dễ sử dụng, rõ ràng, phù hợp với người dùng không chuyên về kỹ thuật. |
| NFR05 | Bảo trì | Mã nguồn phải tổ chức theo module, service và component để thuận tiện nâng cấp. |
| NFR06 | Mở rộng | Cấu trúc hệ thống phải cho phép bổ sung ngôn ngữ, học phần và chức năng mới trong tương lai. |

## 3.5. Bảng Use Case tổng quát

### 3.5.1. Bảng mô tả Use Case cấp cao

| Mã UC | Tên use case | Tác nhân | Mô tả ngắn | Tiền điều kiện | Hậu điều kiện |
|---|---|---|---|---|---|
| UC01 | Xem nội dung công khai | Khách truy cập | Xem trang chủ, bài viết, banner, giảng viên và thư viện ảnh. | Không yêu cầu đăng nhập | Nội dung được hiển thị đầy đủ theo cấu hình công khai. |
| UC02 | Tra cứu chương trình đào tạo | Khách truy cập/Sinh viên | Xem cấu trúc chương trình theo học kỳ, môn học và quan hệ tiên quyết. | Dữ liệu đào tạo đã được nhập | Người dùng tra cứu được lộ trình học tập. |
| UC03 | Xem đề cương môn học | Khách truy cập/Sinh viên | Mở tài liệu đề cương môn học theo liên kết an toàn. | Học phần có tài liệu được công bố | Tài liệu được xem/stream theo quyền truy cập. |
| UC04 | Đăng nhập hệ thống | Người dùng | Xác thực tài khoản để truy cập khu vực quản trị. | Tài khoản tồn tại và hợp lệ | Người dùng được chuyển vào hệ thống theo vai trò. |
| UC05 | Gửi bài viết chờ duyệt | Biên tập viên | Tạo nội dung và chuyển bài sang trạng thái chờ duyệt. | Có quyền viết bài | Bài viết được lưu và ghi nhận lịch sử. |
| UC06 | Duyệt bài viết | Người duyệt | Kiểm tra bài viết và quyết định xuất bản hoặc từ chối. | Bài viết ở trạng thái chờ duyệt | Bài viết đổi trạng thái và gửi thông báo. |
| UC07 | Quản lý đào tạo | Quản trị viên | Thêm/sửa/xóa chương trình đào tạo, học kỳ, học phần và quan hệ học phần. | Đã đăng nhập và có quyền | Dữ liệu đào tạo được cập nhật chính xác. |
| UC08 | Quản lý media | Quản trị viên | Quản lý banner, album và ảnh. | Có quyền quản trị media | Media được cập nhật theo cấu hình. |

### 3.5.2. Bảng mô tả chi tiết use case tiêu biểu

#### Use Case UC05 – Gửi bài viết chờ duyệt

| Thành phần | Nội dung |
|---|---|
| Tác nhân | Biên tập viên |
| Mục tiêu | Gửi một bài viết mới vào quy trình kiểm duyệt trước khi xuất bản. |
| Tiền điều kiện | Người dùng đã đăng nhập và có quyền viết bài. |
| Kích hoạt | Người dùng chọn chức năng tạo bài viết và bấm nút “Gửi duyệt”. |
| Luồng chính | 1) Nhập tiêu đề, nội dung và danh mục. 2) Hệ thống kiểm tra dữ liệu bắt buộc. 3) Hệ thống lưu bài viết ở trạng thái chờ duyệt. 4) Hệ thống ghi lịch sử sự kiện. 5) Hệ thống gửi thông báo đến người duyệt phù hợp. |
| Luồng thay thế | Nếu dữ liệu không hợp lệ, hệ thống hiển thị lỗi và không lưu bài viết. |
| Hậu điều kiện | Bài viết được lưu ở trạng thái `pending_review` và có bản ghi lịch sử. |

#### Use Case UC06 – Duyệt bài viết

| Thành phần | Nội dung |
|---|---|
| Tác nhân | Người duyệt |
| Mục tiêu | Kiểm tra bài viết và quyết định xuất bản hoặc từ chối. |
| Tiền điều kiện | Bài viết đang ở trạng thái chờ duyệt. |
| Kích hoạt | Người duyệt mở trang xem xét bài viết. |
| Luồng chính | 1) Đọc nội dung bài viết. 2) Kiểm tra tiêu đề, hình ảnh và danh mục. 3) Nhập nhận xét nếu cần. 4) Chọn phê duyệt hoặc từ chối. 5) Hệ thống cập nhật trạng thái và gửi thông báo. |
| Luồng thay thế | Nếu bài viết đã bị chỉnh sửa hoặc không còn hợp lệ, hệ thống từ chối thao tác. |
| Hậu điều kiện | Bài viết chuyển sang `published` hoặc `rejected`, đồng thời lưu lịch sử duyệt. |

#### Use Case UC02 – Tra cứu chương trình đào tạo

| Thành phần | Nội dung |
|---|---|
| Tác nhân | Khách truy cập, Sinh viên |
| Mục tiêu | Tra cứu lộ trình học theo ngành/chuyên ngành và học kỳ. |
| Tiền điều kiện | Dữ liệu chương trình đào tạo đã được cấu hình. |
| Kích hoạt | Người dùng chọn chuyên mục chương trình đào tạo. |
| Luồng chính | 1) Chọn ngành hoặc chương trình. 2) Hệ thống tải danh sách học kỳ. 3) Người dùng chọn học kỳ để xem học phần. 4) Hệ thống hiển thị học phần, tín chỉ, tiên quyết và tương đương. |
| Hậu điều kiện | Người dùng hiểu được cấu trúc lộ trình đào tạo của chương trình đã chọn. |

## 3.6. Bảng Activity Diagram

### 3.6.1. Activity Diagram – Quy trình gửi và duyệt bài viết

| Bước | Tác nhân | Hoạt động | Điều kiện rẽ nhánh |
|---|---|---|---|
| A1 | Biên tập viên | Tạo bài viết mới | Bắt đầu quy trình |
| A2 | Biên tập viên | Nhập tiêu đề, nội dung, danh mục | Dữ liệu hợp lệ? |
| A3 | Hệ thống | Kiểm tra dữ liệu và sinh slug | Nếu lỗi thì thông báo và dừng |
| A4 | Hệ thống | Lưu bài viết ở trạng thái chờ duyệt | Nếu bài đã hợp lệ |
| A5 | Hệ thống | Ghi lịch sử gửi duyệt | Thông báo tới người duyệt |
| A6 | Người duyệt | Xem và đánh giá bài viết | Phê duyệt hay từ chối? |
| A7 | Hệ thống | Cập nhật trạng thái xuất bản hoặc từ chối | Nếu từ chối thì lưu lý do |
| A8 | Hệ thống | Gửi email cho tác giả và kết thúc | Hoàn tất quy trình |

### 3.6.2. Activity Diagram – Tra cứu chương trình đào tạo

| Bước | Tác nhân | Hoạt động | Điều kiện rẽ nhánh |
|---|---|---|---|
| B1 | Người dùng | Truy cập trang chương trình đào tạo | Bắt đầu |
| B2 | Người dùng | Chọn ngành/chuyên ngành | Có dữ liệu chương trình? |
| B3 | Hệ thống | Hiển thị danh sách học kỳ | Nếu không có thì thông báo |
| B4 | Người dùng | Chọn học kỳ để xem học phần | Tiếp tục |
| B5 | Hệ thống | Hiển thị học phần, tín chỉ, tiên quyết, tương đương | Có đề cương? |
| B6 | Hệ thống | Hiển thị đường dẫn xem trước/stream đề cương | Kết thúc |

## 3.7. Bảng Sequence Diagram

### 3.7.1. Sequence Diagram – Gửi duyệt bài viết

| Thứ tự | Đối tượng tham gia | Thông điệp/Trạng thái |
|---|---|---|
| 1 | Biên tập viên → Livewire Component | Gửi dữ liệu bài viết |
| 2 | Livewire Component → Hệ thống kiểm tra | Validate dữ liệu |
| 3 | Hệ thống kiểm tra → Model Post | Tạo bản ghi và sinh slug |
| 4 | Model Post → CSDL | Lưu bài viết ở trạng thái chờ duyệt |
| 5 | Service thông báo | Xác định danh sách người duyệt phù hợp |
| 6 | Service thông báo → Mail Queue | Gửi email thông báo |
| 7 | Hệ thống → PostApprovalHistory | Ghi lịch sử gửi duyệt |

### 3.7.2. Sequence Diagram – Phê duyệt bài viết

| Thứ tự | Đối tượng tham gia | Thông điệp/Trạng thái |
|---|---|---|
| 1 | Người duyệt → Livewire Review Component | Mở bài viết cần duyệt |
| 2 | Component → CSDL | Tải chi tiết bài viết và lịch sử |
| 3 | Người duyệt → Component | Gửi quyết định duyệt/từ chối |
| 4 | Component → Model Post | Cập nhật trạng thái bài viết |
| 5 | Model Post → CSDL | Lưu published/rejected |
| 6 | Hệ thống → Mail Queue | Gửi thông báo cho tác giả |
| 7 | Hệ thống → PostApprovalHistory | Lưu hành động duyệt |

## 3.8. Bảng ERD và mô tả quan hệ dữ liệu

### 3.8.1. Bảng thực thể quan hệ chính

| Thực thể | Quan hệ chính | Ghi chú |
|---|---|---|
| `users` | 1–1 với `students`/`lecturers`; 1–n với `posts`; 1–n với `post_approval_histories` | Lưu tài khoản hệ thống. |
| `categories` | 1–n với `posts`; tự liên kết với `parent_id` | Quản lý phân loại bài viết. |
| `posts` | n–1 với `users`; n–1 với `categories`; 1–n với `post_approval_histories` | Lưu bài viết và trạng thái duyệt. |
| `training_programs` | n–1 với `majors`, `program_majors`, `intakes`; 1–n với `program_semesters` | Lưu cấu trúc chương trình đào tạo. |
| `program_semesters` | n–1 với `training_programs`; 1–n với `program_semester_subjects` | Lưu học kỳ của CTĐT. |
| `subjects` | n–1 với `group_subjects`; n–n với `program_semesters`; n–n với `subjects` qua tiên quyết/tương đương | Lưu thông tin học phần. |
| `subject_prerequisites` | n–1 với `training_programs`, `subjects` | Biểu diễn quan hệ tiên quyết theo chương trình. |
| `subject_equivalents` | n–1 với `training_programs`, `subjects` | Biểu diễn quan hệ tương đương theo chương trình. |
| `banners` | độc lập | Phục vụ giao diện. |
| `albums` | 1–n với `album_images` qua bảng liên kết | Quản lý thư viện ảnh. |
| `contact_messages` | độc lập | Lưu thông điệp người dùng gửi qua form liên hệ. |
| `pages` | độc lập | Lưu nội dung trang tĩnh/cấu hình trang. |

### 3.8.2. Mô tả chi tiết các bảng CSDL

| Bảng | Khóa chính | Khóa ngoại | Ý nghĩa | Ràng buộc chính |
|---|---|---|---|---|
| `users` | `id` | - | Lưu thông tin tài khoản đăng nhập, SSO, email và trạng thái hoạt động. | Email duy nhất; mật khẩu được mã hóa; `is_active` kiểu boolean. |
| `students` | `id` | `user_id`, `intake_id`, `major_id`, `program_major_id` | Lưu hồ sơ sinh viên và thông tin học tập cơ bản. | Mỗi sinh viên gắn với một tài khoản; `student_code` nên duy nhất. |
| `lecturers` | `id` | `user_id`, `department_id` | Lưu hồ sơ giảng viên, học vị, chức danh, điện thoại và bộ môn. | Mỗi giảng viên gắn với một tài khoản; `slug` dùng để định tuyến. |
| `departments` | `id` | - | Lưu bộ môn/khoa bộ môn phục vụ phân loại giảng viên. | `slug` duy nhất; có trường sắp xếp. |
| `program_majors` | `id` | - | Lưu nhóm ngành/chương trình ngành ở cấp cao. | `slug` duy nhất; `order` dùng để sắp xếp hiển thị. |
| `majors` | `id` | `program_major_id` | Lưu ngành/chuyên ngành trực thuộc nhóm ngành. | `slug` duy nhất; mỗi major thuộc một program_major. |
| `intakes` | `id` | - | Lưu khóa tuyển sinh hoặc niên khóa. | `name` duy nhất theo ngữ cảnh hiển thị. |
| `training_programs` | `id` | `major_id`, `program_major_id`, `intake_id` | Lưu phiên bản chương trình đào tạo cụ thể. | Có thể lưu `version`, `school_year_start/end`, `total_credits`. |
| `program_semesters` | `id` | `training_program_id` | Lưu học kỳ thuộc chương trình đào tạo. | Mỗi CTĐT chỉ có một bản ghi cho mỗi `semester_no`. |
| `group_subjects` | `id` | - | Lưu nhóm học phần để phân loại môn học. | `sort_order` và `is_active` hỗ trợ hiển thị. |
| `subjects` | `id` | `group_subject_id` | Lưu môn học, mã môn, tên, tín chỉ và đường dẫn đề cương. | `code` duy nhất; tín chỉ dạng số thập phân; `is_active` boolean. |
| `subject_prerequisites` | `id` | `training_program_id`, `subject_id`, `prerequisite_subject_id` | Lưu quan hệ tiên quyết giữa các học phần theo từng CTĐT. | Không trùng bộ ba; một học phần không tự làm tiên quyết của chính nó. |
| `subject_equivalents` | `id` | `training_program_id`, `subject_id`, `equivalent_subject_id` | Lưu quan hệ tương đương giữa các học phần. | Không trùng bộ ba; không tạo vòng lặp tương đương sai lệch. |
| `posts` | `id` | `user_id`, `category_id`, `reviewed_by` | Lưu bài viết, trạng thái duyệt, SEO, lượt xem và thời gian xuất bản. | `slug` duy nhất; `status` theo enum; `views` mặc định 0. |
| `post_approval_histories` | `id` | `post_id`, `actor_id`, `reviewer_id` | Lưu lịch sử biên tập, gửi duyệt, phê duyệt, từ chối, hoàn tác. | `action` theo nghiệp vụ; `scheduled_publish_at` có thể null. |
| `categories` | `id` | `parent_id` | Lưu danh mục bài viết phân cấp. | `slug` duy nhất; hỗ trợ `parent_id` và soft delete. |
| `banners` | `id` | - | Lưu banner giao diện, vị trí hiển thị, thứ tự và nội dung đa ngôn ngữ. | `position` theo danh mục vị trí chuẩn; `is_active` boolean. |
| `albums` | `id` | - | Lưu album ảnh phục vụ truyền thông hoạt động. | `is_featured_home` hỗ trợ hiển thị trang chủ. |
| `album_images` | `id` | `album_id` | Lưu ảnh thuộc album, caption và đường dẫn tệp. | `image_path` bắt buộc; hỗ trợ soft delete. |
| `album_image_album` | - | `album_id`, `album_image_id` | Bảng liên kết nhiều-nhiều giữa album và ảnh (nếu dùng). | Không trùng cặp quan hệ. |
| `contact_messages` | `id` | - | Lưu thông điệp người dùng gửi qua biểu mẫu liên hệ. | `status` theo tập giá trị; lưu `ip_address`, `user_agent`, `recaptcha_score`. |
| `pages` | `id` | - | Lưu nội dung trang tĩnh/cấu hình trang theo `slug`. | `slug` duy nhất; `content_data` lưu dữ liệu đa ngôn ngữ. |

## 3.9. Bảng mô tả ngữ nghĩa các nhóm dữ liệu

| Nhóm dữ liệu | Mục đích sử dụng | Ghi chú thiết kế |
|---|---|---|
| Dữ liệu truyền thông | Quản lý bài viết, banner, album, ảnh và liên hệ | Cần ưu tiên hiển thị công khai, tìm kiếm và phân quyền biên tập. |
| Dữ liệu đào tạo | Quản lý CTĐT, học kỳ, học phần, tiên quyết, tương đương | Cần đảm bảo tính nhất quán giữa các phiên bản và khóa tuyển sinh. |
| Dữ liệu tài khoản | Quản lý người dùng, vai trò, quyền hạn và SSO | Cần bảo mật cao, theo dõi đăng nhập và trạng thái hoạt động. |
| Dữ liệu nội dung tĩnh | Quản lý trang giới thiệu, footer, nội dung cấu hình | Cần linh hoạt trong cập nhật, hỗ trợ đa ngôn ngữ. |
| Dữ liệu nghiệp vụ hỗ trợ | Lịch sử duyệt bài, phản hồi liên hệ, thông báo email | Có vai trò truy vết, kiểm soát và hỗ trợ vận hành. |

---

# CHƯƠNG 4. XÂY DỰNG VÀ TRIỂN KHAI HỆ THỐNG

## 4.1. Kiến trúc triển khai tổng thể

Hệ thống được triển khai theo mô hình ứng dụng web một mã nguồn, trong đó Laravel đóng vai trò trung tâm điều phối, Livewire xử lý tương tác giao diện và MySQL lưu trữ dữ liệu. Cách triển khai này phù hợp với hệ thống cổng thông tin quy mô khoa vì vừa tiết kiệm chi phí phát triển vừa bảo đảm khả năng tổ chức mã nguồn rõ ràng.

Từ các route trong `routes/web.php` có thể thấy hệ thống được chia thành hai vùng lớn: vùng công khai và vùng quản trị. Vùng công khai cung cấp các chức năng giới thiệu, tìm kiếm, bài viết, giảng viên, album ảnh và chương trình đào tạo. Vùng quản trị được bảo vệ bằng xác thực, middleware phân quyền và một số ràng buộc theo vai trò để bảo đảm an toàn dữ liệu.

## 4.2. Mô-đun cổng thông tin công khai

Cổng thông tin công khai đóng vai trò là giao diện chính của hệ thống đối với người truy cập bên ngoài. Các chức năng hiển thị bao gồm trang chủ, giới thiệu, liên hệ, bài viết, tìm kiếm, giảng viên, thư viện ảnh và chương trình đào tạo. Thiết kế giao diện tập trung vào khả năng truy cập nhanh, bố cục rõ ràng và điều hướng thuận tiện.

Trang chủ được xem như trung tâm phân phối thông tin, nơi tập hợp các khối nội dung quan trọng nhất của hệ thống. Trang bài viết cung cấp khả năng đọc tin theo danh mục và chi tiết bài đăng. Trang chương trình đào tạo hỗ trợ tra cứu lộ trình học, còn trang giảng viên và album ảnh góp phần tạo nên hình ảnh nhận diện của khoa trên môi trường số.

## 4.3. Mô-đun xác thực và phân quyền

Hệ thống hỗ trợ đăng nhập, đăng xuất, quên mật khẩu, thiết lập mật khẩu ban đầu và cơ chế SSO nội bộ. Bên cạnh đó, hệ thống sử dụng Spatie Permission để quản lý vai trò và quyền hạn theo chức năng. Các quyền quan trọng bao gồm quản lý bài viết, duyệt bài viết, quản lý media, quản lý người dùng và quản lý đào tạo.

Cơ chế phân quyền không chỉ nhằm chặn truy cập trái phép mà còn phản ánh quy trình làm việc thực tế trong đơn vị. Người viết bài chỉ có thể tạo và gửi nội dung; người duyệt chịu trách nhiệm kiểm tra chất lượng; quản trị viên có thể cấu hình hệ thống và điều phối toàn bộ hoạt động. Việc chia nhỏ quyền hạn như vậy giúp giảm sai sót và tăng tính trách nhiệm của từng nhóm người dùng.

## 4.4. Mô-đun quản lý bài viết

Đây là một trong những phân hệ quan trọng nhất của hệ thống vì liên quan trực tiếp đến công tác truyền thông của khoa. Bài viết được xây dựng với các thuộc tính như tiêu đề, nội dung, trích dẫn, slug, thumbnail, SEO, lượt xem, trạng thái, thời gian xuất bản và lịch sử xử lý. Mỗi bài có thể ở trạng thái nháp, chờ duyệt, xuất bản hoặc từ chối.

Quy trình hoạt động của module bài viết được mô tả như sau: biên tập viên tạo bài, lưu nội dung và gửi duyệt; hệ thống kiểm tra dữ liệu, sinh slug nếu cần và chuyển bài sang trạng thái chờ duyệt; người duyệt mở bài, đánh giá nội dung và quyết định phê duyệt hoặc từ chối; hệ thống ghi nhận lịch sử và gửi thông báo email cho tác giả. Cách tổ chức này bảo đảm nội dung công bố có chất lượng và có thể truy vết rõ ràng.

## 4.5. Mô-đun quản lý lộ trình đào tạo

Mô-đun đào tạo là phần cốt lõi của đề tài. Dữ liệu được tổ chức theo cấu trúc nhiều tầng gồm nhóm ngành, ngành/chuyên ngành, chương trình đào tạo, học kỳ và danh sách học phần. Ngoài ra, hệ thống còn quản lý quan hệ tiên quyết và tương đương giữa các học phần để mô hình hóa đúng quy tắc đào tạo thực tế.

Model `TrainingProgram` liên kết với `Major`, `ProgramMajor`, `Intake` và `ProgramSemester`; model `Subject` lưu thông tin mã môn, tên môn, số tín chỉ, nhóm học phần và đường dẫn đề cương; các bảng `subject_prerequisites` và `subject_equivalents` bảo đảm biểu diễn đúng quan hệ nghiệp vụ. Trên giao diện, dữ liệu đào tạo được trình bày theo học kỳ, giúp người dùng dễ hình dung lộ trình học tập của một chương trình cụ thể.

## 4.6. Mô-đun đề cương môn học

Chức năng xem đề cương môn học được triển khai thông qua hai hình thức: xem trước và stream tài liệu bằng liên kết an toàn có thời hạn. Cách tiếp cận này giúp bảo vệ tài liệu nội bộ, đồng thời vẫn cho phép người dùng hợp lệ truy cập khi cần thiết.

Trong môi trường đào tạo, đề cương môn học là tài liệu có ý nghĩa quan trọng vì nó mô tả nội dung, yêu cầu, chuẩn đầu ra và cách đánh giá của môn học. Do đó, việc kiểm soát truy cập tài liệu là cần thiết để cân bằng giữa tính công khai và tính bảo mật.

## 4.7. Mô-đun banner, album và hình ảnh

Banner và album ảnh là các thành phần hỗ trợ về mặt giao diện và truyền thông. Banner cho phép hiển thị nội dung quảng bá tại các vị trí khác nhau trên trang web, trong khi album và ảnh phục vụ lưu trữ hình ảnh các hoạt động của khoa. Hệ thống thiết kế tách riêng album và ảnh để dễ quản lý, dễ sắp xếp và thuận tiện mở rộng sau này.

## 4.8. Mô-đun nội dung tĩnh và liên hệ

Hệ thống có thêm các trang nội dung tĩnh như giới thiệu, footer, header và các cấu hình giao diện khác. Bên cạnh đó là module liên hệ, cho phép người dùng gửi phản hồi tới đơn vị. Các phản hồi này được lưu lại với trạng thái xử lý, thông tin người gửi và một số dữ liệu kỹ thuật như IP, trình duyệt và điểm reCAPTCHA nhằm hỗ trợ kiểm soát spam.

## 4.9. Tổ chức mã nguồn và xử lý nghiệp vụ

Các xử lý nghiệp vụ được tách thành service để hạn chế việc đặt quá nhiều logic trong component hoặc controller. Cách tổ chức này đặc biệt phù hợp với các chức năng như tìm kiếm bài viết, gửi thông báo, xử lý duyệt bài, đồng bộ quan hệ học phần và lưu tệp tài liệu.

Nhờ tách lớp rõ ràng, hệ thống có thể dễ dàng thay đổi từng phần mà không ảnh hưởng nhiều đến toàn bộ ứng dụng. Đây là điều rất quan trọng đối với một hệ thống có nhiều phân hệ và nhiều nhóm người dùng như cổng thông tin của khoa.

## 4.10. Môi trường phát triển và triển khai

Hệ thống được phát triển trong môi trường Windows, sử dụng Laragon làm máy chủ cục bộ. Các thành phần chính gồm PHP, Composer, Node.js, Vite và MySQL. Mail và queue được sử dụng để xử lý thông báo và các tác vụ nền.

Trong triển khai thực tế, cần cấu hình đúng biến môi trường, quyền ghi file, hàng đợi xử lý mail và lưu trữ tài liệu. Việc chuẩn hóa môi trường triển khai giúp hệ thống vận hành ổn định hơn và hạn chế lỗi phát sinh trong quá trình sử dụng.

---

# CHƯƠNG 5. KIỂM THỬ VÀ ĐÁNH GIÁ

## 5.1. Mục tiêu kiểm thử

Mục tiêu của kiểm thử là xác nhận hệ thống hoạt động đúng theo yêu cầu nghiệp vụ đã phân tích, đặc biệt là các luồng quan trọng gồm: gửi bài viết chờ duyệt, phê duyệt bài viết, gửi thông báo email, quản lý nội dung đào tạo và tra cứu đề cương môn học. Ngoài ra, kiểm thử còn nhằm đánh giá mức độ ổn định, tính chính xác của dữ liệu và khả năng kiểm soát quyền truy cập của hệ thống.

## 5.2. Môi trường kiểm thử

| Thành phần | Mô tả |
|---|---|
| Hệ điều hành | Windows |
| Môi trường phát triển | Laragon |
| Ngôn ngữ lập trình | PHP |
| Framework | Laravel |
| Giao diện tương tác | Livewire |
| Cơ sở dữ liệu | MySQL |
| Công cụ kiểm thử | Laravel Feature Test, Livewire Test, Mail Fake |
| Phương thức kiểm thử | Kiểm thử chức năng, kiểm thử luồng nghiệp vụ, kiểm thử thông báo |

## 5.3. Dữ liệu và phạm vi kiểm thử

Kiểm thử tập trung vào các kịch bản phản ánh sát thực tế sử dụng của hệ thống. Các đối tượng kiểm thử bao gồm người viết bài, người duyệt bài, người dùng công khai và các module đào tạo. Đối với mỗi chức năng, kiểm thử không chỉ quan sát kết quả hiển thị mà còn kiểm tra thay đổi trong cơ sở dữ liệu, trạng thái bài viết và email được sinh ra.

## 5.4. Bảng test case chức năng bài viết

| Mã test | Mô tả | Bước thực hiện | Kết quả mong đợi | Kết quả thực tế | Nhận xét |
|---|---|---|---|---|---|
| TC01 | Biên tập viên tạo bài và gửi duyệt | Nhập nội dung → chọn danh mục → gửi duyệt | Bài viết lưu ở trạng thái chờ duyệt và có lịch sử | Đạt | Quy trình tạo bài ổn định, ghi nhận đúng trạng thái. |
| TC02 | Người duyệt chấp thuận bài viết | Mở bài chờ duyệt → phê duyệt | Bài viết chuyển sang xuất bản, lưu người duyệt | Đạt | Dữ liệu trạng thái và mốc thời gian được cập nhật chính xác. |
| TC03 | Người duyệt từ chối bài viết | Nhập lý do → từ chối | Bài viết chuyển sang từ chối và tác giả được thông báo | Đạt | Hệ thống lưu đúng lý do từ chối. |
| TC04 | Biên tập viên sửa và gửi duyệt lại | Chỉnh sửa nội dung → gửi lại | Bài viết trở lại trạng thái chờ duyệt | Đạt | Luồng chỉnh sửa sau từ chối hoạt động đúng. |

## 5.5. Bảng test case thông báo email

| Mã test | Mô tả | Bước thực hiện | Kết quả mong đợi | Kết quả thực tế | Nhận xét |
|---|---|---|---|---|---|
| TC05 | Gửi bài viết chờ duyệt | Tạo bài và gửi duyệt | Chỉ người duyệt phù hợp nhận email | Đạt | Bộ lọc người nhận hoạt động chính xác. |
| TC06 | Phê duyệt bài viết | Phê duyệt bài viết | Tác giả nhận email bài đã được duyệt | Đạt | Nội dung email phản ánh đúng trạng thái. |
| TC07 | Từ chối bài viết | Từ chối bài viết | Tác giả nhận email kèm lý do từ chối | Đạt | Email thể hiện đúng thông tin phản hồi. |
| TC08 | Chuyển bài viết về chờ duyệt lại | Gửi lại bài bị từ chối | Người liên quan nhận thông báo phù hợp | Đạt | Duy trì được chuỗi liên lạc trong quy trình duyệt. |

## 5.6. Bảng test case module tra cứu đào tạo

| Mã test | Mô tả | Bước thực hiện | Kết quả mong đợi | Kết quả thực tế | Nhận xét |
|---|---|---|---|---|---|
| TC09 | Xem danh sách chương trình đào tạo | Truy cập chuyên mục đào tạo | Hiển thị danh sách CTĐT theo cấu hình | Đạt | Dữ liệu hiển thị rõ ràng, dễ tra cứu. |
| TC10 | Xem chi tiết học kỳ và học phần | Chọn chương trình → chọn học kỳ | Hiển thị đúng danh sách môn và tín chỉ | Đạt | Phân rã dữ liệu theo học kỳ hợp lý. |
| TC11 | Xem đề cương môn học | Mở môn có tài liệu | Tài liệu được xem/stream bằng liên kết an toàn | Đạt | Cơ chế truy cập phù hợp với yêu cầu bảo mật. |
| TC12 | Kiểm tra tiên quyết và tương đương | Mở chi tiết môn | Hiển thị đúng quan hệ học phần | Đạt | Mô hình dữ liệu biểu diễn đúng logic nghiệp vụ. |

## 5.7. Nhận xét kết quả kiểm thử

Kết quả kiểm thử cho thấy các chức năng cốt lõi của hệ thống hoạt động đúng với thiết kế. Luồng bài viết bảo đảm cơ chế kiểm duyệt nhiều bước; luồng thông báo email hoạt động đúng đối tượng; phân hệ đào tạo mô hình hóa được lộ trình học và quan hệ học phần; chức năng đề cương môn học có cơ chế truy cập an toàn. Điều này cho thấy hệ thống đáp ứng được mục tiêu nghiệp vụ đã đặt ra từ đầu.

Về mặt kỹ thuật, việc sử dụng Livewire, Eloquent, queue và mail giúp hệ thống xử lý tốt các thao tác tương tác và thông báo bất đồng bộ. Về mặt nghiệp vụ, quy trình duyệt bài và quản lý đào tạo được tổ chức rõ ràng, có tính truy vết và có khả năng mở rộng trong tương lai.

## 5.8. Hạn chế sau kiểm thử

Bên cạnh các kết quả đạt được, hệ thống vẫn còn một số hạn chế cần tiếp tục hoàn thiện:

- Chưa có bộ dữ liệu kiểm thử lớn cho toàn bộ các khóa đào tạo.
- Chưa triển khai đầy đủ các báo cáo thống kê và trực quan hóa dữ liệu.
- Một số màn hình quản trị có thể tiếp tục tối ưu trải nghiệm người dùng.
- Chưa có kiểm thử hiệu năng quy mô lớn trên môi trường triển khai thực tế.

Những hạn chế này không ảnh hưởng đến tính đúng đắn của các chức năng lõi, nhưng là cơ sở để tiếp tục hoàn thiện trong các giai đoạn phát triển tiếp theo.

---

# KẾT LUẬN VÀ HƯỚNG PHÁT TRIỂN

Đề tài **Xây dựng hệ thống cổng thông tin tích hợp và quản lý lộ trình đào tạo cho khoa Công nghệ Thông tin** đã giải quyết được nhu cầu thực tế về quản trị nội dung, truyền thông học thuật và mô hình hóa chương trình đào tạo trên một nền tảng thống nhất. Từ góc nhìn học thuật, đề tài cho thấy cách tiếp cận phù hợp khi kết hợp cổng thông tin, quy trình duyệt bài viết, phân quyền người dùng và quản lý lộ trình học tập trong cùng một hệ thống web.

Về mặt kỹ thuật, hệ thống tận dụng hiệu quả Laravel, Livewire, MySQL và các thư viện hỗ trợ để xây dựng các phân hệ quan trọng như quản lý bài viết, phân quyền, thông báo, học phần, học kỳ, đề cương môn học và media. Về mặt nghiệp vụ, hệ thống giúp chuẩn hóa quy trình công bố thông tin và biểu diễn lộ trình đào tạo một cách rõ ràng, dễ tra cứu.

Trong thời gian tới, hệ thống có thể tiếp tục phát triển theo các hướng sau:

| Hướng phát triển | Nội dung |
|---|---|
| Phát triển trực quan lộ trình học | Bổ sung sơ đồ lộ trình học theo khóa, học kỳ và điều kiện tiên quyết. |
| Mở rộng báo cáo thống kê | Tích hợp dashboard phân tích nội dung, lượt xem và hoạt động người dùng. |
| Tăng cường tích hợp hệ thống | Kết nối với hệ thống đào tạo cấp trường hoặc nguồn dữ liệu bên ngoài. |
| Cải tiến trải nghiệm người dùng | Tối ưu giao diện quản trị và giao diện công khai trên nhiều thiết bị. |
| Nâng cấp kiểm thử | Bổ sung kiểm thử hiệu năng, kiểm thử tải và kiểm thử bảo mật chuyên sâu. |

Nhìn chung, đề tài có tính ứng dụng cao và phù hợp với xu hướng số hóa quản lý tại các đơn vị đào tạo đại học. Hệ thống sau khi hoàn thiện là một nền tảng có khả năng hỗ trợ thực tiễn cho công tác truyền thông, quản trị nội dung và quản lý đào tạo của khoa Công nghệ Thông tin.

---

# TÀI LIỆU THAM KHẢO

1. Laravel LLC, **Laravel 12.x Documentation - Eloquent ORM**, Laravel.com. [Trực tuyến]. Địa chỉ: https://laravel.com/docs/12.x/eloquent. [Truy cập ngày: 06-05-2026].

2. Laravel LLC, **Laravel 12.x Documentation - Mail**, Laravel.com. [Trực tuyến]. Địa chỉ: https://laravel.com/docs/12.x/mail. [Truy cập ngày: 06-05-2026].

3. Livewire, **Livewire 3.x Documentation**, Livewire.laravel.com. [Trực tuyến]. Địa chỉ: https://livewire.laravel.com/docs. [Truy cập ngày: 06-05-2026].

4. Spatie, **Laravel Permission Documentation**, Spatie.be. [Trực tuyến]. Địa chỉ: https://spatie.be/docs/laravel-permission. [Truy cập ngày: 06-05-2026].

5. Spatie, **Laravel Translatable Documentation**, Spatie.be. [Trực tuyến]. Địa chỉ: https://spatie.be/docs/laravel-translatable. [Truy cập ngày: 06-05-2026].

6. Oracle Corporation, **MySQL 8.4 Reference Manual**, MySQL.com. [Trực tuyến]. Địa chỉ: https://dev.mysql.com/doc/refman/8.4/en/. [Truy cập ngày: 06-05-2026].

7. Laravel LLC, **Laravel 12.x Documentation - Queues**, Laravel.com. [Trực tuyến]. Địa chỉ: https://laravel.com/docs/12.x/queues. [Truy cập ngày: 06-05-2026].
